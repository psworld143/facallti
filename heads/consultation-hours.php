<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Consultation Hours Management';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get head information
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

// Check if head information was found
if (!$head_info) {
    $message = "Head information not found. Please contact administrator.";
    $message_type = "error";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $teacher_id = (int)$_POST['teacher_id'];
                $semester = sanitize_input($_POST['semester']);
                $academic_year = sanitize_input($_POST['academic_year']);
                $day_of_week = sanitize_input($_POST['day_of_week']);
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $room = sanitize_input($_POST['room']);
                $notes = sanitize_input($_POST['notes']);

                if (strtotime($start_time) >= strtotime($end_time)) {
                    $message = "End time must be after start time.";
                    $message_type = "error";
                } elseif (strtotime($end_time) > strtotime('22:00:00')) {
                    $message = "End time cannot be later than 10:00 PM.";
                    $message_type = "error";
                } else {
                    $query = "INSERT INTO consultation_hours (teacher_id, semester, academic_year, day_of_week, start_time, end_time, room, notes, created_by) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "isssssssi", $teacher_id, $semester, $academic_year, $day_of_week, $start_time, $end_time, $room, $notes, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Consultation hours added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding consultation hours.";
                        $message_type = "error";
                    }
                }
                break;

            case 'delete':
                $id = (int)$_POST['id'];
                $query = "UPDATE consultation_hours SET is_active = 0 WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Consultation hours deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting consultation hours.";
                    $message_type = "error";
                }
                break;

            case 'add_leave':
                $leave_teacher_id = (int)$_POST['leave_teacher_id'];
                $leave_date = $_POST['leave_date'];
                $leave_reason = sanitize_input($_POST['leave_reason']);

                // Validate that the date is not in the past
                if (strtotime($leave_date) < strtotime(date('Y-m-d'))) {
                    $message = "Leave date cannot be in the past.";
                    $message_type = "error";
                } else {
                    $query = "INSERT INTO consultation_leave (teacher_id, leave_date, reason) VALUES (?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "iss", $leave_teacher_id, $leave_date, $leave_reason);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Consultation leave added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding consultation leave: " . mysqli_stmt_error($stmt);
                        $message_type = "error";
                    }
                }
                break;

            case 'delete_leave':
                $leave_id = (int)$_POST['leave_id'];
                $query = "DELETE FROM consultation_leave WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $leave_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Consultation leave deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting consultation leave.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get teachers in the same department
$teachers_result = null;
if ($head_info) {
    $teachers_query = "SELECT f.id, f.first_name, f.last_name, f.email, f.department, f.position 
                      FROM faculty f 
                      WHERE f.is_active = 1 AND f.department = ? 
                      ORDER BY f.last_name ASC, f.first_name ASC";

    $teachers_stmt = mysqli_prepare($conn, $teachers_query);
    if ($teachers_stmt) {
        mysqli_stmt_bind_param($teachers_stmt, "s", $head_info['department']);
        if (mysqli_stmt_execute($teachers_stmt)) {
            $teachers_result = mysqli_stmt_get_result($teachers_stmt);
            if (!$teachers_result) {
                $message = "Error retrieving teachers: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            $message = "Error executing teachers query: " . mysqli_stmt_error($teachers_stmt);
            $message_type = "error";
        }
    } else {
        $message = "Error preparing teachers query: " . mysqli_error($conn);
        $message_type = "error";
    }
}

// Get active semester
$active_semester_query = "SELECT name, academic_year FROM semesters WHERE status = 'active' LIMIT 1";
$active_semester_result = mysqli_query($conn, $active_semester_query);
$active_semester = mysqli_fetch_assoc($active_semester_result);

// Get consultation hours (filter by active semester by default)
$consultation_result = null;
if ($head_info) {
    if ($active_semester) {
        $consultation_query = "SELECT ch.*, f.first_name, f.last_name, f.email, f.department 
                              FROM consultation_hours ch 
                              JOIN faculty f ON ch.teacher_id = f.id 
                              WHERE ch.is_active = 1 AND f.department = ? AND ch.semester = ? AND ch.academic_year = ? 
                              ORDER BY f.last_name ASC, f.first_name ASC, ch.day_of_week ASC, ch.start_time ASC";
        $consultation_stmt = mysqli_prepare($conn, $consultation_query);
        if ($consultation_stmt) {
            mysqli_stmt_bind_param($consultation_stmt, "sss", $head_info['department'], $active_semester['name'], $active_semester['academic_year']);
        }
    } else {
        $consultation_query = "SELECT ch.*, f.first_name, f.last_name, f.email, f.department 
                              FROM consultation_hours ch 
                              JOIN faculty f ON ch.teacher_id = f.id 
                              WHERE ch.is_active = 1 AND f.department = ? 
                              ORDER BY f.last_name ASC, f.first_name ASC, ch.day_of_week ASC, ch.start_time ASC";
        $consultation_stmt = mysqli_prepare($conn, $consultation_query);
        if ($consultation_stmt) {
            mysqli_stmt_bind_param($consultation_stmt, "s", $head_info['department']);
        }
    }
    
    if (isset($consultation_stmt) && $consultation_stmt) {
        if (mysqli_stmt_execute($consultation_stmt)) {
            $consultation_result = mysqli_stmt_get_result($consultation_stmt);
            if (!$consultation_result) {
                $message = "Error retrieving consultation hours: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            $message = "Error executing consultation query: " . mysqli_stmt_error($consultation_stmt);
            $message_type = "error";
        }
    } else {
        $message = "Error preparing consultation query: " . mysqli_error($conn);
        $message_type = "error";
    }
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Consultation Hours Management</h1>
                <p class="text-gray-600">Manage consultation hours for teachers in your department</p>
            </div>
                         <div class="flex space-x-3">
                 <button onclick="openAddModal()" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center">
                     <i class="fas fa-plus mr-2"></i>
                     Add Consultation Hours
                 </button>
                 <button onclick="openLeaveModal()" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center">
                     <i class="fas fa-calendar-times mr-2"></i>
                     Add Consultation Leave
                 </button>
             </div>
        </div>
    </div>

    <!-- Message Display -->
    <?php if (!empty($message)): ?>
        <div class="bg-white rounded-lg shadow-sm p-4">
            <div class="flex items-center <?php echo $message_type === 'success' ? 'text-green-800 bg-green-50' : 'text-red-800 bg-red-50'; ?> p-4 rounded-lg">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3"></i>
                <span><?php echo $message; ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Active Semester Info -->
    <?php if ($active_semester): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <i class="fas fa-info-circle text-blue-500 mr-3"></i>
                <div>
                    <p class="text-blue-800 font-medium">Current Active Semester</p>
                    <p class="text-blue-600 text-sm"><?php echo $active_semester['name'] . ' (' . $active_semester['academic_year'] . ')'; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- View Toggle -->
    <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="flex space-x-4">
            <button id="listViewBtn" onclick="showListView()" class="px-4 py-2 bg-seait-orange text-white rounded-lg font-medium">
                <i class="fas fa-list mr-2"></i>List View
            </button>
            <button id="weekViewBtn" onclick="showWeekView()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg font-medium">
                <i class="fas fa-calendar-week mr-2"></i>Week View
            </button>
            <button id="logsViewBtn" onclick="showLogsView()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg font-medium">
                <i class="fas fa-clipboard-list mr-2"></i>Consultation Logs
            </button>
            <button id="reportsViewBtn" onclick="showReportsView()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg font-medium">
                <i class="fas fa-chart-bar mr-2"></i>Response Reports
            </button>
            <button id="leavesViewBtn" onclick="showLeavesView()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg font-medium">
                <i class="fas fa-calendar-times mr-2"></i>Consultation Leaves
            </button>
            <button id="availableTodayViewBtn" onclick="showAvailableTodayView()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg font-medium">
                <i class="fas fa-user-check mr-2"></i>Available Today
            </button>
        </div>
    </div>

    <!-- List View -->
    <div id="listView" class="bg-white rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Consultation Hours - List View</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!$consultation_result || mysqli_num_rows($consultation_result) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                <?php echo $consultation_result ? 'No consultation hours found' : 'Error loading consultation hours'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($consultation = mysqli_fetch_assoc($consultation_result)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo $consultation['first_name'] . ' ' . $consultation['last_name']; ?>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo $consultation['email']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $consultation['semester'] . ' (' . $consultation['academic_year'] . ')'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $consultation['day_of_week']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('g:i A', strtotime($consultation['start_time'])) . ' - ' . date('g:i A', strtotime($consultation['end_time'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php echo $consultation['notes'] ?: '-'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="deleteConsultation(<?php echo $consultation['id']; ?>)" 
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Week View -->
    <div id="weekView" class="bg-white rounded-lg shadow-sm hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Consultation Hours - Week View</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-200 px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Time</th>
                        <th class="border border-gray-200 px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Sunday</th>
                        <th class="border border-gray-200 px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Monday</th>
                        <th class="border border-gray-200 px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Tuesday</th>
                        <th class="border border-gray-200 px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Wednesday</th>
                        <th class="border border-gray-200 px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Thursday</th>
                        <th class="border border-gray-200 px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Friday</th>
                        <th class="border border-gray-200 px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Saturday</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <?php
                    // Generate time slots from 7:00 AM to 8:30 PM with 30-minute intervals
                    $timeSlots = [];
                    $startTime = strtotime('07:00:00');
                    $endTime = strtotime('20:30:00');
                    
                    for ($time = $startTime; $time < $endTime; $time += 1800) { // 1800 seconds = 30 minutes
                        $timeSlots[] = date('H:i:s', $time);
                    }

                    // Get all consultation hours for the week view
                    $week_query = "SELECT ch.*, f.first_name, f.last_name, f.email, f.department 
                                  FROM consultation_hours ch 
                                  JOIN faculty f ON ch.teacher_id = f.id 
                                  WHERE ch.is_active = 1 AND f.department = ? 
                                  ORDER BY ch.day_of_week ASC, ch.start_time ASC";
                    $week_stmt = mysqli_prepare($conn, $week_query);
                    mysqli_stmt_bind_param($week_stmt, "s", $head_info['department']);
                    mysqli_stmt_execute($week_stmt);
                    $week_result = mysqli_stmt_get_result($week_stmt);
                    
                    // Organize consultation hours by day and time
                    $consultation_schedule = [];
                    while ($consultation = mysqli_fetch_assoc($week_result)) {
                        $day = $consultation['day_of_week'];
                        $start = $consultation['start_time'];
                        $end = $consultation['end_time'];
                        $teacher_name = $consultation['first_name'] . ' ' . $consultation['last_name'];
                        $notes = $consultation['notes'];
                        
                        if (!isset($consultation_schedule[$day])) {
                            $consultation_schedule[$day] = [];
                        }
                        
                        $consultation_schedule[$day][] = [
                            'id' => $consultation['id'],
                            'start' => $start,
                            'end' => $end,
                            'teacher' => $teacher_name,
                            'notes' => $notes
                        ];
                    }

                                         $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                     
                     foreach ($timeSlots as $timeSlot) {
                         $timeDisplay = date('g:i', strtotime($timeSlot));
                         $nextTimeSlot = date('H:i:s', strtotime($timeSlot) + 1800);
                         $nextTimeDisplay = date('g:i A', strtotime($nextTimeSlot));
                         ?>
                         <tr>
                             <td class="border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 bg-gray-50 whitespace-nowrap">
                                 <?php echo $timeDisplay . ' - ' . $nextTimeDisplay; ?>
                             </td>
                             <?php foreach ($days as $day) { ?>
                                 <td class="border border-gray-200 px-2 py-1 text-xs min-h-12">
                                     <?php
                                     // Check if there are consultations for this day and time slot
                                     if (isset($consultation_schedule[$day])) {
                                         $consultations_in_slot = [];
                                         
                                         // Collect all consultations for this time slot
                                         foreach ($consultation_schedule[$day] as $consultation) {
                                             $consultation_start = $consultation['start'];
                                             $consultation_end = $consultation['end'];
                                             
                                             // Check if this time slot overlaps with the consultation hours
                                             if (($timeSlot >= $consultation_start && $timeSlot < $consultation_end) ||
                                                 ($nextTimeSlot > $consultation_start && $nextTimeSlot <= $consultation_end) ||
                                                 ($timeSlot <= $consultation_start && $nextTimeSlot >= $consultation_end)) {
                                                 
                                                 // Get initials from teacher name
                                                 $teacher_name = $consultation['teacher'];
                                                 $name_parts = explode(' ', $teacher_name);
                                                 $initials = '';
                                                 if (count($name_parts) >= 2) {
                                                     $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                                                 } else {
                                                     $initials = strtoupper(substr($teacher_name, 0, 2));
                                                 }
                                                 
                                                 $consultations_in_slot[] = [
                                                     'initials' => $initials,
                                                     'teacher_name' => $teacher_name,
                                                     'notes' => $consultation['notes'],
                                                     'id' => $consultation['id']
                                                 ];
                                             }
                                         }
                                         
                                         // Display consultations with overlapping effect and different colors
                                         if (!empty($consultations_in_slot)) {
                                             $colors = [
                                                 'bg-seait-orange',
                                                 'bg-blue-500',
                                                 'bg-green-500',
                                                 'bg-purple-500',
                                                 'bg-red-500',
                                                 'bg-indigo-500',
                                                 'bg-pink-500',
                                                 'bg-yellow-500'
                                             ];
                                             
                                             $hover_colors = [
                                                 'hover:bg-orange-600',
                                                 'hover:bg-blue-600',
                                                 'hover:bg-green-600',
                                                 'hover:bg-purple-600',
                                                 'hover:bg-red-600',
                                                 'hover:bg-indigo-600',
                                                 'hover:bg-pink-600',
                                                 'hover:bg-yellow-600'
                                             ];
                                             
                                             echo '<div class="flex justify-center relative">';
                                                                                           foreach ($consultations_in_slot as $index => $consultation) {
                                                  $color_class = $colors[$index % count($colors)];
                                                  $hover_class = $hover_colors[$index % count($hover_colors)];
                                                  $z_index = $index + 1; // First index (0) gets lowest z-index (1), last gets highest
                                                 ?>
                                                 <div class="<?php echo $color_class; ?> text-white p-1 rounded-full w-8 h-8 flex items-center justify-center text-xs cursor-pointer <?php echo $hover_class; ?> transition-all duration-200 shadow-md border-2 border-white relative" 
                                                      style="z-index: <?php echo $z_index; ?>; margin-left: <?php echo $index > 0 ? '-8px' : '0'; ?>;"
                                                      title="<?php echo htmlspecialchars($consultation['teacher_name']); ?><?php if ($consultation['notes']) { ?> - <?php echo htmlspecialchars($consultation['notes']); ?><?php } ?>"
                                                      onclick="showTeacherDetails(<?php echo $consultation['id']; ?>)">
                                                     <span class="font-medium"><?php echo $consultation['initials']; ?></span>
                                                 </div>
                                                 <?php
                                             }
                                             echo '</div>';
                                         }
                                     }
                                     ?>
                                 </td>
                             <?php } ?>
                         </tr>
                     <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Consultation Logs View -->
    <div id="logsView" class="bg-white rounded-lg shadow-sm hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Consultation Logs - Department Faculty</h3>
            <p class="text-sm text-gray-600 mt-1">View consultation request history and statistics for faculty in your department</p>
        </div>
        <div class="p-6">
            <?php
            // Get consultation logs for faculty in the department
            $logs_query = "SELECT 
                            cr.id,
                            cr.student_name,
                            cr.student_dept,
                            cr.request_time,
                            cr.response_time,
                            cr.response_duration_seconds,
                            cr.status,
                            cr.decline_reason,
                            f.first_name,
                            f.last_name,
                            f.email
                          FROM consultation_requests cr
                          JOIN faculty f ON cr.teacher_id = f.id
                          WHERE f.department = ? AND cr.request_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                          ORDER BY cr.request_time DESC
                          LIMIT 100";
            
            $logs_stmt = mysqli_prepare($conn, $logs_query);
            mysqli_stmt_bind_param($logs_stmt, "s", $head_info['department']);
            mysqli_stmt_execute($logs_stmt);
            $logs_result = mysqli_stmt_get_result($logs_stmt);
            
            if (mysqli_num_rows($logs_result) === 0): ?>
                <div class="text-center py-12">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <i class="fas fa-clipboard-list text-4xl"></i>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No consultation logs</h3>
                    <p class="mt-1 text-sm text-gray-500">No consultation logs found for the last 30 days.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Response Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($log = mysqli_fetch_assoc($logs_result)): ?>
                                <tr class="hover:bg-gray-50">
                                    <!-- Teacher Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user-tie text-white text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo $log['first_name'] . ' ' . $log['last_name']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?php echo $log['email']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Student Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user-graduate text-white text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($log['student_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($log['student_dept']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Requested Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            $request_date = new DateTime($log['request_time']);
                                            $now = new DateTime();
                                            $diff = $request_date->diff($now);
                                            
                                            if ($diff->days == 0) {
                                                if ($diff->h == 0) {
                                                    echo $diff->i . ' min ago';
                                                } else {
                                                    echo $diff->h . 'h ' . $diff->i . 'm ago';
                                                }
                                            } elseif ($diff->days == 1) {
                                                echo 'Yesterday at ' . $request_date->format('g:i A');
                                            } else {
                                                echo $request_date->format('M j, g:i A');
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    
                                    <!-- Response Time Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            if ($log['response_time']) {
                                                $response_date = new DateTime($log['response_time']);
                                                $response_diff = $request_date->diff($response_date);
                                                
                                                if ($response_diff->days > 0) {
                                                    echo $response_date->format('M j, g:i A');
                                                } else {
                                                    echo $response_date->format('g:i A');
                                                }
                                            } else {
                                                echo '<span class="text-gray-400">Pending</span>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    
                                    <!-- Duration Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php
                                            if ($log['response_duration_seconds'] && $log['response_duration_seconds'] > 0) {
                                                // Duration is stored in seconds
                                                $duration_seconds = $log['response_duration_seconds'];
                                                
                                                if ($duration_seconds < 60) {
                                                    echo $duration_seconds . ' seconds';
                                                } elseif ($duration_seconds < 3600) {
                                                    $minutes = floor($duration_seconds / 60);
                                                    $seconds = $duration_seconds % 60;
                                                    echo $minutes . ' min ' . $seconds . ' sec';
                                                } else {
                                                    $hours = floor($duration_seconds / 3600);
                                                    $minutes = floor(($duration_seconds % 3600) / 60);
                                                    echo $hours . 'h ' . $minutes . 'm';
                                                }
                                            } else {
                                                echo '<span class="text-gray-400">-</span>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    
                                    <!-- Status Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                            'accepted' => 'bg-green-100 text-green-800 border-green-200',
                                            'declined' => 'bg-red-100 text-red-800 border-red-200',
                                            'completed' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'cancelled' => 'bg-gray-100 text-gray-800 border-gray-200'
                                        ];
                                        $status_color = $status_colors[$log['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                        ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border <?php echo $status_color; ?>">
                                            <i class="fas fa-circle text-xs mr-1"></i>
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Actions Column -->
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewLogDetails(<?php echo $log['id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-seait-orange hover:bg-orange-600 text-white text-xs font-medium rounded-lg transition-colors duration-200">
                                                <i class="fas fa-eye mr-1"></i>
                                                View
                                            </button>
                                            <?php if ($log['status'] === 'declined' && $log['decline_reason']): ?>
                                                <button onclick="viewDeclineReason('<?php echo htmlspecialchars($log['decline_reason']); ?>')" 
                                                        class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-medium rounded-lg transition-colors duration-200">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Reason
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Response Reports View -->
    <div id="reportsView" class="bg-white rounded-lg shadow-sm hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Response Time Reports - Department Faculty</h3>
            <p class="text-sm text-gray-600 mt-1">Analyze faculty response times and identify quick responders vs. slow responders</p>
        </div>
        <div class="p-6">
            <?php
            // Get faculty response time statistics for the department
            $reports_query = "SELECT 
                                f.id,
                                f.first_name,
                                f.last_name,
                                f.email,
                                f.position,
                                COUNT(cr.id) as total_requests,
                                COUNT(CASE WHEN cr.status IN ('accepted', 'declined') THEN 1 END) as responded_requests,
                                AVG(CASE WHEN cr.response_duration_seconds > 0 THEN cr.response_duration_seconds END) as avg_response_time,
                                MIN(CASE WHEN cr.response_duration_seconds > 0 THEN cr.response_duration_seconds END) as fastest_response,
                                MAX(CASE WHEN cr.response_duration_seconds > 0 THEN cr.response_duration_seconds END) as slowest_response,
                                COUNT(CASE WHEN cr.response_duration_seconds <= 300 THEN 1 END) as quick_responses,
                                COUNT(CASE WHEN cr.response_duration_seconds > 900 THEN 1 END) as slow_responses
                              FROM faculty f
                              LEFT JOIN consultation_requests cr ON f.id = cr.teacher_id 
                                AND cr.request_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                AND cr.status IN ('accepted', 'declined')
                              WHERE f.department = ? AND f.is_active = 1
                              GROUP BY f.id, f.first_name, f.last_name, f.email, f.position
                              HAVING responded_requests > 0
                              ORDER BY avg_response_time ASC";
            
            $reports_stmt = mysqli_prepare($conn, $reports_query);
            mysqli_stmt_bind_param($reports_stmt, "s", $head_info['department']);
            mysqli_stmt_execute($reports_stmt);
            $reports_result = mysqli_stmt_get_result($reports_stmt);
            
            if (mysqli_num_rows($reports_result) === 0): ?>
                <div class="text-center py-12">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <i class="fas fa-chart-bar text-4xl"></i>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No response data available</h3>
                    <p class="mt-1 text-sm text-gray-500">No consultation responses found for the last 30 days.</p>
                </div>
            <?php else: ?>
                <!-- Quick Responders Section -->
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-green-700 mb-4 flex items-center">
                        <i class="fas fa-bolt mr-2"></i>
                        Quick Responders (≤ 5 minutes)
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php 
                        mysqli_data_seek($reports_result, 0);
                        $quick_count = 0;
                        while ($faculty = mysqli_fetch_assoc($reports_result)): 
                            if ($faculty['avg_response_time'] <= 300 && $quick_count < 6): // 5 minutes = 300 seconds
                                $quick_count++;
                        ?>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user-tie text-white text-sm"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-medium text-green-900">
                                                <?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?>
                                            </h5>
                                            <p class="text-sm text-green-600"><?php echo $faculty['position']; ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-green-700">
                                            <?php 
                                            $avg_minutes = round($faculty['avg_response_time'] / 60, 1);
                                            echo $avg_minutes . ' min';
                                            ?>
                                        </div>
                                        <div class="text-xs text-green-600">avg response</div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div class="text-green-700">
                                        <span class="font-medium"><?php echo $faculty['responded_requests']; ?></span> requests
                                    </div>
                                    <div class="text-green-700">
                                        <span class="font-medium"><?php echo $faculty['quick_responses']; ?></span> quick
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endwhile; 
                        if ($quick_count === 0): ?>
                            <div class="col-span-full text-center py-8 text-gray-500">
                                <i class="fas fa-info-circle text-2xl mb-2"></i>
                                <p>No faculty with average response time ≤ 5 minutes</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Slow Responders Section -->
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-red-700 mb-4 flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        Slow Responders (> 15 minutes)
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php 
                        mysqli_data_seek($reports_result, 0);
                        $slow_count = 0;
                        while ($faculty = mysqli_fetch_assoc($reports_result)): 
                            if ($faculty['avg_response_time'] > 900 && $slow_count < 6): // 15 minutes = 900 seconds
                                $slow_count++;
                        ?>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user-tie text-white text-sm"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-medium text-red-900">
                                                <?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?>
                                            </h5>
                                            <p class="text-sm text-red-600"><?php echo $faculty['position']; ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-red-700">
                                            <?php 
                                            $avg_minutes = round($faculty['avg_response_time'] / 60, 1);
                                            echo $avg_minutes . ' min';
                                            ?>
                                        </div>
                                        <div class="text-xs text-red-600">avg response</div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div class="text-red-700">
                                        <span class="font-medium"><?php echo $faculty['responded_requests']; ?></span> requests
                                    </div>
                                    <div class="text-red-700">
                                        <span class="font-medium"><?php echo $faculty['slow_responses']; ?></span> slow
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endwhile; 
                        if ($slow_count === 0): ?>
                            <div class="col-span-full text-center py-8 text-gray-500">
                                <i class="fas fa-info-circle text-2xl mb-2"></i>
                                <p>No faculty with average response time > 15 minutes</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detailed Statistics Table -->
                <div>
                    <h4 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                        <i class="fas fa-table mr-2"></i>
                        Detailed Response Statistics
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Requests</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responded</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Response</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fastest</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slowest</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                mysqli_data_seek($reports_result, 0);
                                while ($faculty = mysqli_fetch_assoc($reports_result)): 
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-user-tie text-white text-xs"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500"><?php echo $faculty['email']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $faculty['position']; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $faculty['total_requests']; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $faculty['responded_requests']; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php 
                                            if ($faculty['avg_response_time']) {
                                                $avg_minutes = round($faculty['avg_response_time'] / 60, 1);
                                                echo $avg_minutes . ' min';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php 
                                            if ($faculty['fastest_response']) {
                                                if ($faculty['fastest_response'] < 60) {
                                                    echo $faculty['fastest_response'] . ' sec';
                                                } else {
                                                    echo round($faculty['fastest_response'] / 60, 1) . ' min';
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php 
                                            if ($faculty['slowest_response']) {
                                                $slowest_minutes = round($faculty['slowest_response'] / 60, 1);
                                                echo $slowest_minutes . ' min';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <?php
                                            $performance_ratio = $faculty['responded_requests'] > 0 ? 
                                                ($faculty['quick_responses'] / $faculty['responded_requests']) * 100 : 0;
                                            
                                            if ($performance_ratio >= 80) {
                                                $color = 'bg-green-100 text-green-800 border-green-200';
                                                $icon = 'fas fa-star';
                                            } elseif ($performance_ratio >= 60) {
                                                $color = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                $icon = 'fas fa-check';
                                            } else {
                                                $color = 'bg-red-100 text-red-800 border-red-200';
                                                $icon = 'fas fa-exclamation-triangle';
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border <?php echo $color; ?>">
                                                <i class="<?php echo $icon; ?> text-xs mr-1"></i>
                                                <?php echo round($performance_ratio); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Consultation Leaves View -->
    <div id="leavesView" class="bg-white rounded-lg shadow-sm hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Consultation Leaves - Department Faculty</h3>
            <p class="text-sm text-gray-600 mt-1">View scheduled consultation leaves for faculty in your department</p>
        </div>
        <div class="p-6">
            <?php
            // Get consultation leaves for faculty in the department
            $leaves_query = "SELECT 
                              cl.id,
                              cl.leave_date,
                              cl.reason,
                              cl.created_at,
                              f.first_name,
                              f.last_name,
                              f.email,
                              f.position
                            FROM consultation_leave cl
                            JOIN faculty f ON cl.teacher_id = f.id
                            WHERE f.department = ? AND cl.leave_date >= CURDATE()
                            ORDER BY cl.leave_date ASC";
            
            $leaves_result = null;
            if ($head_info) {
                $leaves_stmt = mysqli_prepare($conn, $leaves_query);
                if ($leaves_stmt) {
                    mysqli_stmt_bind_param($leaves_stmt, "s", $head_info['department']);
                    if (mysqli_stmt_execute($leaves_stmt)) {
                        $leaves_result = mysqli_stmt_get_result($leaves_stmt);
                        if (!$leaves_result) {
                            $message = "Error retrieving consultation leaves: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    } else {
                        $message = "Error executing leaves query: " . mysqli_stmt_error($leaves_stmt);
                        $message_type = "error";
                    }
                } else {
                    $message = "Error preparing leaves query: " . mysqli_error($conn);
                    $message_type = "error";
                }
            }
            
            if (!$leaves_result || mysqli_num_rows($leaves_result) === 0): ?>
                <div class="text-center py-12">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <i class="fas fa-calendar-times text-4xl"></i>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No consultation leaves</h3>
                    <p class="mt-1 text-sm text-gray-500"><?php echo $leaves_result ? 'No consultation leaves scheduled for the future.' : 'Error loading consultation leaves'; ?></p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($leave = mysqli_fetch_assoc($leaves_result)): ?>
                                <tr class="hover:bg-gray-50">
                                    <!-- Teacher Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user-tie text-white text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?php echo $leave['email']; ?></div>
                                                <div class="text-xs text-gray-400"><?php echo $leave['position']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Leave Date Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            $leave_date = new DateTime($leave['leave_date']);
                                            $today = new DateTime();
                                            $diff = $leave_date->diff($today);
                                            
                                            if ($diff->days == 0) {
                                                echo '<span class="text-red-600 font-medium">Today</span>';
                                            } elseif ($diff->days == 1) {
                                                echo '<span class="text-orange-600 font-medium">Tomorrow</span>';
                                            } else {
                                                echo $leave_date->format('M j, Y (l)');
                                            }
                                            ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $diff->days; ?> days from now
                                        </div>
                                    </td>
                                    
                                    <!-- Reason Column -->
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-gray-900 max-w-xs">
                                            <?php echo htmlspecialchars($leave['reason']); ?>
                                        </div>
                                    </td>
                                    
                                    <!-- Status Column -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php
                                        $leave_date = new DateTime($leave['leave_date']);
                                        $today = new DateTime();
                                        
                                        if ($leave_date < $today) {
                                            $status_color = 'bg-gray-100 text-gray-800 border-gray-200';
                                            $status_text = 'Completed';
                                            $icon = 'fas fa-check-circle';
                                        } elseif ($leave_date->format('Y-m-d') === $today->format('Y-m-d')) {
                                            $status_color = 'bg-red-100 text-red-800 border-red-200';
                                            $status_text = 'Today';
                                            $icon = 'fas fa-exclamation-circle';
                                        } else {
                                            $status_color = 'bg-blue-100 text-blue-800 border-blue-200';
                                            $status_text = 'Upcoming';
                                            $icon = 'fas fa-clock';
                                        }
                                        ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border <?php echo $status_color; ?>">
                                            <i class="<?php echo $icon; ?> text-xs mr-1"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Actions Column -->
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="deleteLeave(<?php echo $leave['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800 transition-colors duration-200">
                                            <i class="fas fa-trash mr-1"></i>
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Available Today View -->
    <div id="availableTodayView" class="bg-white rounded-lg shadow-sm hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Available Teachers Today - <?php echo date('l, F j, Y'); ?></h3>
            <p class="text-sm text-gray-600 mt-1">Teachers who have consultation hours scheduled for today and are available for student consultations</p>
        </div>
        <div class="p-6">
            <?php
            // Get current day of week
            $current_day = date('l'); // Returns Monday, Tuesday, etc.
            $current_time = date('H:i:s'); // Current time in HH:MM:SS format
            $current_date = date('Y-m-d'); // Current date
            
            // Get teachers available today (considering consultation hours and leaves)
            // Group by teacher to avoid duplicates and concatenate time ranges
            $available_today_query = "SELECT 
                                        f.id,
                                        f.first_name,
                                        f.last_name,
                                        f.email,
                                        f.position,
                                        f.department,
                                        GROUP_CONCAT(DISTINCT ch.day_of_week) as day_of_week,
                                        GROUP_CONCAT(DISTINCT CONCAT(TIME_FORMAT(ch.start_time, '%h:%i %p'), ' - ', TIME_FORMAT(ch.end_time, '%h:%i %p')) ORDER BY ch.start_time SEPARATOR ', ') as time_ranges,
                                        GROUP_CONCAT(DISTINCT ch.room ORDER BY ch.start_time SEPARATOR ', ') as rooms,
                                        GROUP_CONCAT(DISTINCT ch.notes ORDER BY ch.start_time SEPARATOR ' | ') as all_notes,
                                        MIN(ch.start_time) as earliest_start,
                                        MAX(ch.end_time) as latest_end,
                                        CASE 
                                            WHEN TIME(NOW()) BETWEEN MIN(ch.start_time) AND MAX(ch.end_time) THEN 'active'
                                            WHEN TIME(NOW()) < MIN(ch.start_time) THEN 'upcoming'
                                            ELSE 'ended'
                                        END as status
                                      FROM faculty f
                                      JOIN consultation_hours ch ON f.id = ch.teacher_id
                                      LEFT JOIN consultation_leave cl ON f.id = cl.teacher_id AND cl.leave_date = CURDATE()
                                      WHERE f.department = ? 
                                        AND f.is_active = 1 
                                        AND ch.is_active = 1
                                        AND ch.day_of_week = ?
                                        AND cl.id IS NULL
                                      GROUP BY f.id, f.first_name, f.last_name, f.email, f.position, f.department
                                      ORDER BY 
                                        CASE 
                                            WHEN TIME(NOW()) BETWEEN MIN(ch.start_time) AND MAX(ch.end_time) THEN 1
                                            WHEN TIME(NOW()) < MIN(ch.start_time) THEN 2
                                            ELSE 3
                                        END,
                                        MIN(ch.start_time) ASC";
            
            $available_today_result = null;
            if ($head_info) {
                $available_today_stmt = mysqli_prepare($conn, $available_today_query);
                if ($available_today_stmt) {
                    mysqli_stmt_bind_param($available_today_stmt, "ss", $head_info['department'], $current_day);
                    if (mysqli_stmt_execute($available_today_stmt)) {
                        $available_today_result = mysqli_stmt_get_result($available_today_stmt);
                    }
                }
            }
            
            if (!$available_today_result || mysqli_num_rows($available_today_result) === 0): ?>
                <div class="text-center py-12">
                    <div class="mx-auto h-16 w-16 text-gray-400 mb-4">
                        <i class="fas fa-user-clock text-6xl"></i>
                    </div>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No Teachers Available Today</h3>
                    <p class="mt-1 text-sm text-gray-500">No teachers have consultation hours scheduled for <?php echo $current_day; ?> or they may be on leave.</p>
                    <div class="mt-6">
                        <button onclick="openAddModal()" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center mx-auto">
                            <i class="fas fa-plus mr-2"></i>
                            Add Consultation Hours
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <?php
                    $active_count = 0;
                    $upcoming_count = 0;
                    $ended_count = 0;
                    
                    // Count statuses
                    mysqli_data_seek($available_today_result, 0);
                    while ($teacher = mysqli_fetch_assoc($available_today_result)) {
                        switch ($teacher['status']) {
                            case 'active':
                                $active_count++;
                                break;
                            case 'upcoming':
                                $upcoming_count++;
                                break;
                            case 'ended':
                                $ended_count++;
                                break;
                        }
                    }
                    ?>
                    
                    <!-- Active Now Card -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-user-check text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-green-900"><?php echo $active_count; ?></h4>
                                <p class="text-sm text-green-700">Active Now</p>
                                <p class="text-xs text-green-600">Available for consultation</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Card -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-blue-900"><?php echo $upcoming_count; ?></h4>
                                <p class="text-sm text-blue-700">Upcoming</p>
                                <p class="text-xs text-blue-600">Will be available later</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ended Card -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-gray-500 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-user-times text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900"><?php echo $ended_count; ?></h4>
                                <p class="text-sm text-gray-700">Ended</p>
                                <p class="text-xs text-gray-600">Consultation hours finished</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teachers List -->
                <div class="space-y-4">
                    <?php 
                    mysqli_data_seek($available_today_result, 0);
                    while ($teacher = mysqli_fetch_assoc($available_today_result)): 
                        // Determine status colors and icons
                        switch ($teacher['status']) {
                            case 'active':
                                $status_color = 'bg-green-100 text-green-800 border-green-200';
                                $status_icon = 'fas fa-circle text-green-500';
                                $status_text = 'Available Now';
                                $card_border = 'border-green-300';
                                break;
                            case 'upcoming':
                                $status_color = 'bg-blue-100 text-blue-800 border-blue-200';
                                $status_icon = 'fas fa-clock text-blue-500';
                                $status_text = 'Starts at ' . date('g:i A', strtotime($teacher['earliest_start']));
                                $card_border = 'border-blue-300';
                                break;
                            case 'ended':
                                $status_color = 'bg-gray-100 text-gray-800 border-gray-200';
                                $status_icon = 'fas fa-check-circle text-gray-500';
                                $status_text = 'Ended at ' . date('g:i A', strtotime($teacher['latest_end']));
                                $card_border = 'border-gray-300';
                                break;
                            default:
                                $status_color = 'bg-gray-100 text-gray-800 border-gray-200';
                                $status_icon = 'fas fa-question-circle text-gray-500';
                                $status_text = 'Unknown';
                                $card_border = 'border-gray-300';
                        }
                    ?>
                    <div class="bg-white border-2 <?php echo $card_border; ?> rounded-lg p-6 hover:shadow-lg transition-all duration-200">
                        <div class="flex items-start justify-between">
                            <!-- Teacher Info -->
                            <div class="flex items-start space-x-4 flex-1">
                                <div class="w-12 h-12 bg-seait-orange rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-user-tie text-white text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <h4 class="text-lg font-semibold text-gray-900">
                                            <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                                        </h4>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border <?php echo $status_color; ?>">
                                            <i class="<?php echo $status_icon; ?> text-xs mr-1"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                                        <div class="space-y-1">
                                            <p><i class="fas fa-envelope mr-2 text-seait-orange"></i><?php echo $teacher['email']; ?></p>
                                            <p><i class="fas fa-briefcase mr-2 text-seait-orange"></i><?php echo $teacher['position']; ?></p>
                                        </div>
                                        <div class="space-y-1">
                                            <p><i class="fas fa-clock mr-2 text-seait-orange"></i><?php echo $teacher['time_ranges']; ?></p>
                                            <p><i class="fas fa-door-open mr-2 text-seait-orange"></i><?php echo $teacher['rooms']; ?></p>
                                        </div>
                                    </div>
                                    <?php if (!empty($teacher['all_notes']) && $teacher['all_notes'] !== ''): ?>
                                    <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                                        <p class="text-sm text-gray-700">
                                            <i class="fas fa-sticky-note mr-2 text-seait-orange"></i>
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($teacher['all_notes']); ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex flex-col space-y-2 ml-4">
                                <?php if ($teacher['status'] === 'active'): ?>
                                <div class="flex items-center text-green-600 text-sm font-medium">
                                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></div>
                                    Ready for Students
                                </div>
                                <?php elseif ($teacher['status'] === 'upcoming'): ?>
                                <div class="flex items-center text-blue-600 text-sm font-medium">
                                    <i class="fas fa-hourglass-half mr-2"></i>
                                    Starts Soon
                                </div>
                                <?php else: ?>
                                <div class="flex items-center text-gray-600 text-sm font-medium">
                                    <i class="fas fa-check mr-2"></i>
                                    Session Ended
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="consultationModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-5 mx-auto p-0 border-0 w-full max-w-4xl shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="consultationModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-seait-orange to-orange-500 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-plus text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Add Consultation Hours</h3>
                        <p class="text-orange-100 text-sm">Schedule teacher consultation time</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="text-white hover:text-orange-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-8">
            <form method="POST" id="consultationForm">
                <input type="hidden" name="action" value="add">
                
                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <!-- Left Column - Basic Information -->
                    <div class="space-y-6">
                        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-seait-orange"></i>
                                Basic Information
                            </h4>
                            
                            <!-- Teacher Selection -->
                            <div class="mb-6">
                                <label for="teacher_id" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <i class="fas fa-user-tie mr-2 text-seait-orange"></i>
                                    Select Teacher *
                                </label>
                                <div class="relative">
                                    <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                        <option value="">Choose a teacher...</option>
                                        <?php 
                                        // Reset the teachers result pointer
                                        mysqli_data_seek($teachers_result, 0);
                                        while ($teacher = mysqli_fetch_assoc($teachers_result)): 
                                        ?>
                                            <option value="<?php echo $teacher['id']; ?>">
                                                <?php echo $teacher['last_name'] . ', ' . $teacher['first_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Semester & Academic Year -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="semester" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                        <i class="fas fa-calendar mr-2 text-seait-orange"></i>
                                        Semester *
                                    </label>
                                    <div class="relative">
                                        <select name="semester" required class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                            <option value="">Select semester...</option>
                                            <option value="First Semester" <?php echo ($active_semester && $active_semester['name'] === 'First Semester') ? 'selected' : ''; ?>>First Semester</option>
                                            <option value="Second Semester" <?php echo ($active_semester && $active_semester['name'] === 'Second Semester') ? 'selected' : ''; ?>>Second Semester</option>
                                            <option value="Summer" <?php echo ($active_semester && $active_semester['name'] === 'Summer') ? 'selected' : ''; ?>>Summer</option>
                                        </select>
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-calendar-alt text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="academic_year" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                        <i class="fas fa-graduation-cap mr-2 text-seait-orange"></i>
                                        Academic Year *
                                    </label>
                                    <div class="relative">
                                        <input type="text" name="academic_year" required placeholder="2024-2025" 
                                               value="<?php echo $active_semester ? htmlspecialchars($active_semester['academic_year']) : ''; ?>"
                                               class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-calendar text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Schedule Information -->
                    <div class="space-y-6">
                        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-clock mr-2 text-seait-orange"></i>
                                Schedule Details
                            </h4>
                            
                            <!-- Day Selection -->
                            <div class="mb-6">
                                <label for="day_of_week" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <i class="fas fa-calendar-day mr-2 text-seait-orange"></i>
                                    Day of Week *
                                </label>
                                <div class="relative">
                                    <select name="day_of_week" required class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                        <option value="">Select day...</option>
                                        <option value="Monday">Monday</option>
                                        <option value="Tuesday">Tuesday</option>
                                        <option value="Wednesday">Wednesday</option>
                                        <option value="Thursday">Thursday</option>
                                        <option value="Friday">Friday</option>
                                        <option value="Saturday">Saturday</option>
                                        <option value="Sunday">Sunday</option>
                                    </select>
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-calendar-day text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Selection -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="start_time" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                        <i class="fas fa-clock mr-2 text-seait-orange"></i>
                                        Start Time *
                                    </label>
                                    <div class="relative">
                                        <input type="time" name="start_time" required 
                                               class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-play text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>
                                                        <div>
                            <label for="end_time" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-clock mr-2 text-seait-orange"></i>
                                End Time *
                            </label>
                            <div class="relative">
                                <input type="time" name="end_time" required max="22:00"
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-stop text-gray-400"></i>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Maximum time allowed: 10:00 PM</p>
                        </div>
                            </div>

                            <!-- Room -->
                            <div>
                                <label for="room" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <i class="fas fa-door-open mr-2 text-seait-orange"></i>
                                    Room *
                                </label>
                                <div class="relative">
                                    <input type="text" name="room" required placeholder="e.g., Room 101, Lab 2, Office 3A" 
                                           class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-building text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Full Width - Additional Notes -->
                <div class="mt-8">
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-sticky-note mr-2 text-seait-orange"></i>
                            Additional Information
                        </h4>
                        
                        <div>
                            <label for="notes" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-edit mr-2 text-seait-orange"></i>
                                Additional Notes
                            </label>
                            <div class="relative">
                                <textarea name="notes" rows="4" placeholder="Enter any additional notes about the consultation hours, special instructions, or requirements..." 
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm resize-none"></textarea>
                                <div class="absolute top-3 left-3 pointer-events-none">
                                    <i class="fas fa-edit text-gray-400"></i>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Optional: Add any special instructions or requirements for students</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 rounded-xl p-6 mt-8 border border-gray-200">
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0 sm:space-x-4">
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-info-circle mr-2 text-seait-orange"></i>
                            <span class="text-sm">All fields marked with * are required</span>
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" onclick="closeModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105 flex items-center">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </button>
                            <button type="submit" class="bg-seait-orange hover:bg-orange-600 text-white px-8 py-3 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105 flex items-center">
                                <i class="fas fa-save mr-2"></i>
                                Save Consultation Hours
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-20 mx-auto p-0 border-0 w-full max-w-md shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="deleteModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Confirm Delete</h3>
                        <p class="text-red-100 text-sm">This action cannot be undone</p>
                    </div>
                </div>
                <button onclick="closeDeleteModal()" class="text-white hover:text-red-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6">
            <div class="flex items-start">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4 mt-1">
                    <i class="fas fa-trash text-red-600"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">Delete Consultation Hours</h4>
                    <p class="text-gray-600 leading-relaxed">Are you sure you want to delete this consultation hours record? This action will permanently remove the consultation schedule and cannot be undone.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId" value="">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-trash mr-2"></i>
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Teacher Details Modal -->
<div id="teacherDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-10 mx-auto p-0 border-0 w-full max-w-md shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="teacherDetailsModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-seait-orange to-orange-500 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-user-tie text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Teacher Details</h3>
                        <p class="text-orange-100 text-sm">Consultation Information</p>
                    </div>
                </div>
                <button onclick="closeTeacherDetailsModal()" class="text-white hover:text-orange-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 space-y-6">
            <!-- Teacher Info Section -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-user mr-2 text-seait-orange"></i>
                    Teacher Information
                </h4>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-seait-orange bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-id-card text-seait-orange text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500 font-medium">Name</p>
                            <p id="teacherName" class="text-gray-900 font-semibold">-</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-seait-orange bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-envelope text-seait-orange text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500 font-medium">Email</p>
                            <p id="teacherEmail" class="text-gray-900">-</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-seait-orange bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-building text-seait-orange text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500 font-medium">Department</p>
                            <p id="teacherDepartment" class="text-gray-900">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Consultation Schedule Section -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-calendar-alt mr-2 text-seait-orange"></i>
                    Consultation Schedule
                </h4>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-seait-orange bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-calendar-day text-seait-orange text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500 font-medium">Day</p>
                            <p id="consultationDay" class="text-gray-900 font-semibold">-</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-seait-orange bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-clock text-seait-orange text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500 font-medium">Time</p>
                            <p id="consultationTime" class="text-gray-900 font-semibold">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-sticky-note mr-2 text-seait-orange"></i>
                    Additional Notes
                </h4>
                <div class="flex items-start">
                    <div class="w-8 h-8 bg-seait-orange bg-opacity-10 rounded-full flex items-center justify-center mr-3 mt-1">
                        <i class="fas fa-info text-seait-orange text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p id="consultationNotes" class="text-gray-900 text-sm leading-relaxed">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <div class="flex justify-end">
                <button onclick="closeTeacherDetailsModal()" class="bg-seait-orange hover:bg-orange-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>
                    Got it
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div id="logDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-10 mx-auto p-0 border-0 w-full max-w-2xl shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="logDetailsModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-seait-orange to-orange-500 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-clipboard-list text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Consultation Log Details</h3>
                        <p class="text-orange-100 text-sm">Detailed information about the consultation request</p>
                    </div>
                </div>
                <button onclick="closeLogDetailsModal()" class="text-white hover:text-orange-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6">
            <div id="logDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <div class="flex justify-end">
                <button onclick="closeLogDetailsModal()" class="bg-seait-orange hover:bg-orange-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Decline Reason Modal -->
<div id="declineReasonModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-10 mx-auto p-0 border-0 w-full max-w-md shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="declineReasonModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-info-circle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Decline Reason</h3>
                        <p class="text-red-100 text-sm">Reason provided by the teacher</p>
                    </div>
                </div>
                <button onclick="closeDeclineReasonModal()" class="text-white hover:text-red-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3 mt-1">
                        <i class="fas fa-exclamation-triangle text-red-600 text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p id="declineReasonText" class="text-red-800 text-sm leading-relaxed">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <div class="flex justify-end">
                <button onclick="closeDeclineReasonModal()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Consultation Leave Modal -->
<div id="leaveModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-5 mx-auto p-0 border-0 w-full max-w-2xl shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="leaveModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-seait-orange to-orange-500 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-calendar-times text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Add Consultation Leave</h3>
                        <p class="text-orange-100 text-sm">Schedule teacher consultation leave</p>
                    </div>
                </div>
                <button onclick="closeLeaveModal()" class="text-white hover:text-orange-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-8">
            <form method="POST">
                <input type="hidden" name="action" value="add_leave">
                
                <div class="space-y-6">
                    <!-- Teacher Selection -->
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-user-tie mr-2 text-seait-orange"></i>
                            Teacher Information
                        </h4>
                        
                        <label for="leave_teacher_id" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-user-tie mr-2 text-seait-orange"></i>
                            Select Teacher *
                        </label>
                        <div class="relative">
                            <select name="leave_teacher_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                <option value="">Choose a teacher...</option>
                                <?php 
                                // Reset the teachers result pointer
                                mysqli_data_seek($teachers_result, 0);
                                while ($teacher = mysqli_fetch_assoc($teachers_result)): 
                                ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo $teacher['last_name'] . ', ' . $teacher['first_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Leave Details -->
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-calendar-times mr-2 text-seait-orange"></i>
                            Leave Details
                        </h4>
                        
                        <!-- Leave Date -->
                        <div class="mb-6">
                            <label for="leave_date" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-calendar mr-2 text-seait-orange"></i>
                                Leave Date *
                            </label>
                            <div class="relative">
                                <input type="date" name="leave_date" required 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-calendar-alt text-gray-400"></i>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Select the date when the teacher will be on consultation leave</p>
                        </div>

                        <!-- Reason -->
                        <div>
                            <label for="leave_reason" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-comment mr-2 text-seait-orange"></i>
                                Reason for Leave *
                            </label>
                            <div class="relative">
                                <textarea name="leave_reason" required rows="4" 
                                          placeholder="Enter the reason for consultation leave..."
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white shadow-sm resize-none"></textarea>
                                <div class="absolute top-3 left-3 flex items-start pointer-events-none">
                                    <i class="fas fa-comment-alt text-gray-400"></i>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Provide a detailed reason for the consultation leave</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 rounded-xl p-6 mt-8 border border-gray-200">
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0 sm:space-x-4">
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-info-circle mr-2 text-seait-orange"></i>
                            <span class="text-sm">All fields marked with * are required</span>
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" onclick="closeLeaveModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105 flex items-center">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </button>
                            <button type="submit" class="bg-seait-orange hover:bg-orange-600 text-white px-8 py-3 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105 flex items-center">
                                <i class="fas fa-save mr-2"></i>
                                Save Consultation Leave
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Consultation Leave Modal -->
<div id="deleteLeaveModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-20 mx-auto p-0 border-0 w-full max-w-md shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="deleteLeaveModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Delete Consultation Leave</h3>
                        <p class="text-red-100 text-sm">This action cannot be undone</p>
                    </div>
                </div>
                <button onclick="closeDeleteLeaveModal()" class="text-white hover:text-red-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6">
            <div class="flex items-start">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4 mt-1">
                    <i class="fas fa-calendar-times text-red-600"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">Remove Consultation Leave</h4>
                    <p class="text-gray-600 leading-relaxed">Are you sure you want to delete this consultation leave record? This action will permanently remove the scheduled consultation leave and cannot be undone.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <form method="POST">
                <input type="hidden" name="action" value="delete_leave">
                <input type="hidden" name="leave_id" id="deleteLeaveId" value="">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteLeaveModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-trash mr-2"></i>
                        Delete Leave
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    const modal = document.getElementById('consultationModal');
    const modalContent = document.getElementById('consultationModalContent');
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('consultationModal');
    const modalContent = document.getElementById('consultationModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function openLeaveModal() {
    const modal = document.getElementById('leaveModal');
    const modalContent = document.getElementById('leaveModalContent');
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeLeaveModal() {
    const modal = document.getElementById('leaveModal');
    const modalContent = document.getElementById('leaveModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function closeDeleteLeaveModal() {
    const modal = document.getElementById('deleteLeaveModal');
    const modalContent = document.getElementById('deleteLeaveModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function deleteConsultation(id) {
    const modal = document.getElementById('deleteModal');
    const modalContent = document.getElementById('deleteModalContent');
    
    document.getElementById('deleteId').value = id;
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    const modalContent = document.getElementById('deleteModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function deleteLeave(leaveId) {
    const modal = document.getElementById('deleteLeaveModal');
    const modalContent = document.getElementById('deleteLeaveModalContent');
    
    document.getElementById('deleteLeaveId').value = leaveId;
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function showListView() {
    document.getElementById('listView').classList.remove('hidden');
    document.getElementById('weekView').classList.add('hidden');
    document.getElementById('logsView').classList.add('hidden');
    document.getElementById('reportsView').classList.add('hidden');
    document.getElementById('leavesView').classList.add('hidden');
    document.getElementById('availableTodayView').classList.add('hidden');
    document.getElementById('listViewBtn').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('listViewBtn').classList.add('bg-seait-orange', 'text-white');
    document.getElementById('weekViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('weekViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('logsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('logsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('reportsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('reportsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('leavesViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('leavesViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('availableTodayViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('availableTodayViewBtn').classList.add('bg-gray-300', 'text-gray-700');
}

function showWeekView() {
    document.getElementById('weekView').classList.remove('hidden');
    document.getElementById('listView').classList.add('hidden');
    document.getElementById('logsView').classList.add('hidden');
    document.getElementById('reportsView').classList.add('hidden');
    document.getElementById('leavesView').classList.add('hidden');
    document.getElementById('availableTodayView').classList.add('hidden');
    document.getElementById('weekViewBtn').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('weekViewBtn').classList.add('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('logsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('logsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('reportsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('reportsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('leavesViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('leavesViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('availableTodayViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('availableTodayViewBtn').classList.add('bg-gray-300', 'text-gray-700');
}

function showLogsView() {
    document.getElementById('logsView').classList.remove('hidden');
    document.getElementById('listView').classList.add('hidden');
    document.getElementById('weekView').classList.add('hidden');
    document.getElementById('reportsView').classList.add('hidden');
    document.getElementById('leavesView').classList.add('hidden');
    document.getElementById('availableTodayView').classList.add('hidden');
    document.getElementById('logsViewBtn').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('logsViewBtn').classList.add('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('weekViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('weekViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('reportsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('reportsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('leavesViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('leavesViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('availableTodayViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('availableTodayViewBtn').classList.add('bg-gray-300', 'text-gray-700');
}

function showReportsView() {
    document.getElementById('reportsView').classList.remove('hidden');
    document.getElementById('listView').classList.add('hidden');
    document.getElementById('weekView').classList.add('hidden');
    document.getElementById('logsView').classList.add('hidden');
    document.getElementById('leavesView').classList.add('hidden');
    document.getElementById('availableTodayView').classList.add('hidden');
    document.getElementById('reportsViewBtn').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('reportsViewBtn').classList.add('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('weekViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('weekViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('logsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('logsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('leavesViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('leavesViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('availableTodayViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('availableTodayViewBtn').classList.add('bg-gray-300', 'text-gray-700');
}

function showLeavesView() {
    document.getElementById('leavesView').classList.remove('hidden');
    document.getElementById('listView').classList.add('hidden');
    document.getElementById('weekView').classList.add('hidden');
    document.getElementById('logsView').classList.add('hidden');
    document.getElementById('reportsView').classList.add('hidden');
    document.getElementById('availableTodayView').classList.add('hidden');
    document.getElementById('leavesViewBtn').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('leavesViewBtn').classList.add('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('weekViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('weekViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('logsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('logsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('reportsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('reportsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('availableTodayViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('availableTodayViewBtn').classList.add('bg-gray-300', 'text-gray-700');
}

function showAvailableTodayView() {
    document.getElementById('availableTodayView').classList.remove('hidden');
    document.getElementById('listView').classList.add('hidden');
    document.getElementById('weekView').classList.add('hidden');
    document.getElementById('logsView').classList.add('hidden');
    document.getElementById('reportsView').classList.add('hidden');
    document.getElementById('leavesView').classList.add('hidden');
    document.getElementById('availableTodayViewBtn').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('availableTodayViewBtn').classList.add('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('weekViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('weekViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('logsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('logsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('reportsViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('reportsViewBtn').classList.add('bg-gray-300', 'text-gray-700');
    document.getElementById('leavesViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('leavesViewBtn').classList.add('bg-gray-300', 'text-gray-700');
}

function showTeacherDetails(consultationId) {
    // Show modal with animation
    const modal = document.getElementById('teacherDetailsModal');
    const modalContent = document.getElementById('teacherDetailsModalContent');
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
    
    // Show loading state
    document.getElementById('teacherName').textContent = 'Loading...';
    document.getElementById('teacherEmail').textContent = '';
    document.getElementById('teacherDepartment').textContent = '';
    document.getElementById('consultationDay').textContent = '';
    document.getElementById('consultationTime').textContent = '';
    document.getElementById('consultationNotes').textContent = '';
    
    // Fetch consultation details via AJAX
    fetch('get-consultation-details.php?id=' + consultationId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                closeTeacherDetailsModal();
                return;
            }
            
            // Populate modal with fetched data
            document.getElementById('teacherName').textContent = data.teacher_name || 'N/A';
            document.getElementById('teacherEmail').textContent = data.email || 'N/A';
            document.getElementById('teacherDepartment').textContent = data.department || 'N/A';
            document.getElementById('consultationDay').textContent = data.day_of_week || 'N/A';
            document.getElementById('consultationTime').textContent = (data.start_time && data.end_time) ? data.start_time + ' - ' + data.end_time : 'N/A';
            document.getElementById('consultationNotes').textContent = data.notes || '-';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading consultation details');
            closeTeacherDetailsModal();
        });
}

function closeTeacherDetailsModal() {
    const modal = document.getElementById('teacherDetailsModal');
    const modalContent = document.getElementById('teacherDetailsModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Close modals when clicking outside
window.onclick = function(event) {
    const consultationModal = document.getElementById('consultationModal');
    const deleteModal = document.getElementById('deleteModal');
    const teacherDetailsModal = document.getElementById('teacherDetailsModal');
    const logDetailsModal = document.getElementById('logDetailsModal');
    const declineReasonModal = document.getElementById('declineReasonModal');
    const leaveModal = document.getElementById('leaveModal');
    const deleteLeaveModal = document.getElementById('deleteLeaveModal');
    
    if (event.target === consultationModal) {
        closeModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
    if (event.target === teacherDetailsModal) {
        closeTeacherDetailsModal();
    }
    if (event.target === logDetailsModal) {
        closeLogDetailsModal();
    }
    if (event.target === declineReasonModal) {
        closeDeclineReasonModal();
    }
    if (event.target === leaveModal) {
        closeLeaveModal();
    }
    if (event.target === deleteLeaveModal) {
        closeDeleteLeaveModal();
    }
}

function viewLogDetails(logId) {
    // Show modal with animation
    const modal = document.getElementById('logDetailsModal');
    const modalContent = document.getElementById('logDetailsModalContent');
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
    
    // Show loading state
    document.getElementById('logDetailsContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-seait-orange"></i><p class="mt-2 text-gray-600">Loading details...</p></div>';
    
    // Fetch log details via AJAX
    fetch('get-consultation-log-details.php?id=' + logId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('logDetailsContent').innerHTML = '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl"></i><p class="mt-2">Error: ' + data.error + '</p></div>';
                return;
            }
            
            // Populate modal with fetched data
            const content = `
                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">Teacher Information</h4>
                        <p class="text-sm text-gray-700"><strong>Name:</strong> ${data.teacher_name || 'N/A'}</p>
                        <p class="text-sm text-gray-700"><strong>Email:</strong> ${data.teacher_email || 'N/A'}</p>
                        <p class="text-sm text-gray-700"><strong>Department:</strong> ${data.teacher_department || 'N/A'}</p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">Student Information</h4>
                        <p class="text-sm text-gray-700"><strong>Name:</strong> ${data.student_name || 'N/A'}</p>
                        <p class="text-sm text-gray-700"><strong>Department:</strong> ${data.student_dept || 'N/A'}</p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">Request Details</h4>
                        <p class="text-sm text-gray-700"><strong>Request Time:</strong> ${data.request_time || 'N/A'}</p>
                        <p class="text-sm text-gray-700"><strong>Response Time:</strong> ${data.response_time || 'N/A'}</p>
                        <p class="text-sm text-gray-700"><strong>Response Duration:</strong> ${data.response_duration_formatted || 'N/A'}</p>
                        <p class="text-sm text-gray-700"><strong>Status:</strong> <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(data.status)}">${data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'N/A'}</span></p>
                        ${data.decline_reason ? `<p class="text-sm text-gray-700"><strong>Decline Reason:</strong> <span class="text-red-600">${data.decline_reason}</span></p>` : ''}
                    </div>
                </div>
            `;
            
            document.getElementById('logDetailsContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('logDetailsContent').innerHTML = '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl"></i><p class="mt-2">Error loading log details</p></div>';
        });
}

function closeLogDetailsModal() {
    const modal = document.getElementById('logDetailsModal');
    const modalContent = document.getElementById('logDetailsModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function viewDeclineReason(reason) {
    // Show modal with animation
    const modal = document.getElementById('declineReasonModal');
    const modalContent = document.getElementById('declineReasonModalContent');
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
    
    // Set the reason text
    document.getElementById('declineReasonText').textContent = reason;
}

function closeDeclineReasonModal() {
    const modal = document.getElementById('declineReasonModal');
    const modalContent = document.getElementById('declineReasonModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function getStatusColor(status) {
    const statusColors = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'accepted': 'bg-green-100 text-green-800',
        'declined': 'bg-red-100 text-red-800',
        'completed': 'bg-blue-100 text-blue-800',
        'cancelled': 'bg-gray-100 text-gray-800'
    };
    return statusColors[status] || 'bg-gray-100 text-gray-800';
}
</script>


