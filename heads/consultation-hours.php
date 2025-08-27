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
                $notes = sanitize_input($_POST['notes']);

                if (strtotime($start_time) >= strtotime($end_time)) {
                    $message = "End time must be after start time.";
                    $message_type = "error";
                } else {
                    $query = "INSERT INTO consultation_hours (teacher_id, semester, academic_year, day_of_week, start_time, end_time, notes, created_by) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "issssssi", $teacher_id, $semester, $academic_year, $day_of_week, $start_time, $end_time, $notes, $user_id);

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
        }
    }
}

// Get teachers in the same department
$teachers_query = "SELECT f.id, f.first_name, f.last_name, f.email, f.department, f.position 
                  FROM faculty f 
                  WHERE f.is_active = 1 AND f.department = ? 
                  ORDER BY f.last_name ASC, f.first_name ASC";

$teachers_stmt = mysqli_prepare($conn, $teachers_query);
mysqli_stmt_bind_param($teachers_stmt, "s", $head_info['department']);
mysqli_stmt_execute($teachers_stmt);
$teachers_result = mysqli_stmt_get_result($teachers_stmt);

// Get active semester
$active_semester_query = "SELECT name, academic_year FROM semesters WHERE status = 'active' LIMIT 1";
$active_semester_result = mysqli_query($conn, $active_semester_query);
$active_semester = mysqli_fetch_assoc($active_semester_result);

// Get consultation hours (filter by active semester by default)
if ($active_semester) {
    $consultation_query = "SELECT ch.*, f.first_name, f.last_name, f.email, f.department 
                          FROM consultation_hours ch 
                          JOIN faculty f ON ch.teacher_id = f.id 
                          WHERE ch.is_active = 1 AND f.department = ? AND ch.semester = ? AND ch.academic_year = ? 
                          ORDER BY f.last_name ASC, f.first_name ASC, ch.day_of_week ASC, ch.start_time ASC";
    $consultation_stmt = mysqli_prepare($conn, $consultation_query);
    mysqli_stmt_bind_param($consultation_stmt, "sss", $head_info['department'], $active_semester['name'], $active_semester['academic_year']);
} else {
    $consultation_query = "SELECT ch.*, f.first_name, f.last_name, f.email, f.department 
                          FROM consultation_hours ch 
                          JOIN faculty f ON ch.teacher_id = f.id 
                          WHERE ch.is_active = 1 AND f.department = ? 
                          ORDER BY f.last_name ASC, f.first_name ASC, ch.day_of_week ASC, ch.start_time ASC";
    $consultation_stmt = mysqli_prepare($conn, $consultation_query);
    mysqli_stmt_bind_param($consultation_stmt, "s", $head_info['department']);
}
mysqli_stmt_execute($consultation_stmt);
$consultation_result = mysqli_stmt_get_result($consultation_stmt);

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
            <button onclick="openAddModal()" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Add Consultation Hours
            </button>
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
                    <?php if (mysqli_num_rows($consultation_result) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No consultation hours found</td>
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
                    // Generate time slots from 7:00 AM to 6:00 PM with 30-minute intervals
                    $timeSlots = [];
                    $startTime = strtotime('07:00:00');
                    $endTime = strtotime('18:00:00');
                    
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
</div>

<!-- Add Modal -->
<div id="consultationModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-10 mx-auto p-0 border-0 w-full max-w-lg shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="consultationModalContent">
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
        <div class="p-6">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="space-y-6">
                    <!-- Teacher Selection -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <label for="teacher_id" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-user-tie mr-2 text-seait-orange"></i>
                            Select Teacher *
                        </label>
                        <div class="relative">
                            <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white">
                                <option value="">Choose a teacher...</option>
                                <?php while ($teacher = mysqli_fetch_assoc($teachers_result)): ?>
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
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <label for="semester" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-calendar mr-2 text-seait-orange"></i>
                                Semester *
                            </label>
                            <div class="relative">
                                <select name="semester" required class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white">
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
                        <div class="bg-gray-50 rounded-lg p-4">
                            <label for="academic_year" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-graduation-cap mr-2 text-seait-orange"></i>
                                Academic Year *
                            </label>
                            <div class="relative">
                                <input type="text" name="academic_year" required placeholder="2024-2025" 
                                       value="<?php echo $active_semester ? htmlspecialchars($active_semester['academic_year']) : ''; ?>"
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-calendar text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Day Selection -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <label for="day_of_week" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-calendar-day mr-2 text-seait-orange"></i>
                            Day of Week *
                        </label>
                        <div class="relative">
                            <select name="day_of_week" required class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white">
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
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <label for="start_time" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-clock mr-2 text-seait-orange"></i>
                                Start Time *
                            </label>
                            <div class="relative">
                                <input type="time" name="start_time" required 
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-play text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <label for="end_time" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-clock mr-2 text-seait-orange"></i>
                                End Time *
                            </label>
                            <div class="relative">
                                <input type="time" name="end_time" required 
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-stop text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <label for="notes" class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-sticky-note mr-2 text-seait-orange"></i>
                            Additional Notes
                        </label>
                        <div class="relative">
                            <textarea name="notes" rows="3" placeholder="Enter any additional notes about the consultation hours..." 
                                      class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent bg-white resize-none"></textarea>
                            <div class="absolute top-3 left-3 pointer-events-none">
                                <i class="fas fa-edit text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-times mr-2"></i>
                    Cancel
                </button>
                <button type="submit" form="consultationForm" class="bg-seait-orange hover:bg-orange-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i>
                    Save Consultation
                </button>
            </div>
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

function showListView() {
    document.getElementById('listView').classList.remove('hidden');
    document.getElementById('weekView').classList.add('hidden');
    document.getElementById('listViewBtn').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('listViewBtn').classList.add('bg-seait-orange', 'text-white');
    document.getElementById('weekViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('weekViewBtn').classList.add('bg-gray-300', 'text-gray-700');
}

function showWeekView() {
    document.getElementById('weekView').classList.remove('hidden');
    document.getElementById('listView').classList.add('hidden');
    document.getElementById('weekViewBtn').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('weekViewBtn').classList.add('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.remove('bg-seait-orange', 'text-white');
    document.getElementById('listViewBtn').classList.add('bg-gray-300', 'text-gray-700');
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
    
    if (event.target === consultationModal) {
        closeModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
    if (event.target === teacherDetailsModal) {
        closeTeacherDetailsModal();
    }
}
</script>


