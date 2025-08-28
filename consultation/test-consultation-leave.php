<?php
session_start();
require_once '../config/database.php';

// Set page title
$page_title = 'Consultation Leave Test';

// Get current date and day
$current_date = date('Y-m-d');
$current_day = date('l');
$current_time = date('H:i:s');

// Get active semester
$semester_query = "SELECT name, academic_year FROM semesters WHERE status = 'active' LIMIT 1";
$semester_result = mysqli_query($conn, $semester_query);
$active_semester = null;
$active_academic_year = null;

if ($semester_result && mysqli_num_rows($semester_result) > 0) {
    $semester_row = mysqli_fetch_assoc($semester_result);
    $active_semester = $semester_row['name'];
    $active_academic_year = $semester_row['academic_year'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SEAIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-seait-orange to-orange-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-12 w-auto mr-4">
                    <div>
                        <h1 class="text-2xl font-bold text-white">Consultation Leave Test</h1>
                        <p class="text-orange-100">Testing Teacher Availability with Leave System</p>
                    </div>
                </div>
                <div class="text-white text-right">
                    <p class="text-sm"><?php echo date('l, F j, Y'); ?></p>
                    <p class="text-sm"><?php echo date('g:i:s A'); ?></p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Current Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-seait-orange mr-2"></i>
                Current System Status
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-day text-blue-600 text-xl mr-3"></i>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Current Date</p>
                            <p class="text-lg font-bold text-blue-800"><?php echo $current_date; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-green-600 text-xl mr-3"></i>
                        <div>
                            <p class="text-sm text-green-600 font-medium">Current Time</p>
                            <p class="text-lg font-bold text-green-800"><?php echo $current_time; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-graduation-cap text-purple-600 text-xl mr-3"></i>
                        <div>
                            <p class="text-sm text-purple-600 font-medium">Active Semester</p>
                            <p class="text-lg font-bold text-purple-800">
                                <?php echo $active_semester ? $active_semester . ' (' . $active_academic_year . ')' : 'Not Set'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- All Teachers with Consultation Hours -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-users text-blue-600 mr-2"></i>
                        All Teachers with Consultation Hours Today
                    </h3>
                    <p class="text-sm text-gray-600">Teachers who have scheduled consultation hours (including those on leave)</p>
                </div>
                <div class="p-6">
                    <?php
                    // Query to see all teachers with consultation hours for today
                    $all_teachers_query = "SELECT 
                        f.id,
                        f.first_name,
                        f.last_name,
                        f.department,
                        ch.start_time,
                        ch.end_time,
                        ch.room,
                        CASE 
                            WHEN cl.teacher_id IS NOT NULL THEN 'On Leave'
                            ELSE 'Available'
                        END as status
                    FROM faculty f
                    INNER JOIN consultation_hours ch ON f.id = ch.teacher_id
                    LEFT JOIN consultation_leave cl ON f.id = cl.teacher_id AND cl.leave_date = CURDATE()
                    WHERE f.is_active = 1 
                    AND ch.day_of_week = ?
                    AND ch.is_active = 1
                    " . ($active_semester ? "AND ch.semester = ?" : "") . "
                    " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                    ORDER BY f.first_name, f.last_name";

                    $all_teachers_stmt = mysqli_prepare($conn, $all_teachers_query);
                    if ($all_teachers_stmt) {
                        $param_types = "s";
                        $param_values = [$current_day];
                        
                        if ($active_semester) {
                            $param_types .= "s";
                            $param_values[] = $active_semester;
                        }
                        if ($active_academic_year) {
                            $param_types .= "s";
                            $param_values[] = $active_academic_year;
                        }
                        
                        mysqli_stmt_bind_param($all_teachers_stmt, $param_types, ...$param_values);
                        mysqli_stmt_execute($all_teachers_stmt);
                        $all_teachers_result = mysqli_stmt_get_result($all_teachers_stmt);
                        
                        if (mysqli_num_rows($all_teachers_result) > 0): ?>
                            <div class="space-y-3">
                                <?php while ($teacher = mysqli_fetch_assoc($all_teachers_result)): ?>
                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg <?php echo $teacher['status'] === 'On Leave' ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'; ?>">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-bold text-gray-600">
                                                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                </p>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($teacher['department']); ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo date('g:i A', strtotime($teacher['start_time'])) . ' - ' . date('g:i A', strtotime($teacher['end_time'])); ?>
                                                    <?php if ($teacher['room']): ?>
                                                        | <?php echo htmlspecialchars($teacher['room']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $teacher['status'] === 'On Leave' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                <i class="fas <?php echo $teacher['status'] === 'On Leave' ? 'fa-times-circle' : 'fa-check-circle'; ?> mr-1"></i>
                                                <?php echo $teacher['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500">No teachers have consultation hours scheduled for today.</p>
                            </div>
                        <?php endif;
                    } else {
                        echo '<p class="text-red-500">Error preparing query.</p>';
                    }
                    ?>
                </div>
            </div>

            <!-- Available Teachers Only -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        Available Teachers Only
                    </h3>
                    <p class="text-sm text-gray-600">Teachers who are available for consultation (not on leave)</p>
                </div>
                <div class="p-6">
                    <?php
                    // Query to see only available teachers (not on leave)
                    $available_teachers_query = "SELECT 
                        f.id,
                        f.first_name,
                        f.last_name,
                        f.department,
                        ch.start_time,
                        ch.end_time,
                        ch.room
                    FROM faculty f
                    INNER JOIN consultation_hours ch ON f.id = ch.teacher_id
                    WHERE f.is_active = 1 
                    AND ch.day_of_week = ?
                    AND ch.is_active = 1
                    " . ($active_semester ? "AND ch.semester = ?" : "") . "
                    " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                    AND f.id NOT IN (
                        SELECT teacher_id 
                        FROM consultation_leave 
                        WHERE leave_date = CURDATE()
                    )
                    ORDER BY f.first_name, f.last_name";

                    $available_teachers_stmt = mysqli_prepare($conn, $available_teachers_query);
                    if ($available_teachers_stmt) {
                        $param_types = "s";
                        $param_values = [$current_day];
                        
                        if ($active_semester) {
                            $param_types .= "s";
                            $param_values[] = $active_semester;
                        }
                        if ($active_academic_year) {
                            $param_types .= "s";
                            $param_values[] = $active_academic_year;
                        }
                        
                        mysqli_stmt_bind_param($available_teachers_stmt, $param_types, ...$param_values);
                        mysqli_stmt_execute($available_teachers_stmt);
                        $available_teachers_result = mysqli_stmt_get_result($available_teachers_stmt);
                        
                        if (mysqli_num_rows($available_teachers_result) > 0): ?>
                            <div class="space-y-3">
                                <?php while ($teacher = mysqli_fetch_assoc($available_teachers_result)): ?>
                                    <div class="flex items-center justify-between p-3 border border-green-200 rounded-lg bg-green-50">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-bold text-white">
                                                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                </p>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($teacher['department']); ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo date('g:i A', strtotime($teacher['start_time'])) . ' - ' . date('g:i A', strtotime($teacher['end_time'])); ?>
                                                    <?php if ($teacher['room']): ?>
                                                        | <?php echo htmlspecialchars($teacher['room']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Available
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500">No teachers are available for consultation today.</p>
                            </div>
                        <?php endif;
                    } else {
                        echo '<p class="text-red-500">Error preparing query.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Consultation Leave Information -->
        <div class="bg-white rounded-lg shadow-md mt-8">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-calendar-times text-red-600 mr-2"></i>
                    Teachers on Consultation Leave Today
                </h3>
                <p class="text-sm text-gray-600">Teachers who are on leave and unavailable for consultation</p>
            </div>
            <div class="p-6">
                <?php
                // Query to see teachers on leave today
                $leave_query = "SELECT 
                    cl.id,
                    cl.leave_date,
                    cl.reason,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position
                FROM consultation_leave cl
                JOIN faculty f ON cl.teacher_id = f.id
                WHERE cl.leave_date = CURDATE()
                ORDER BY f.first_name, f.last_name";

                $leave_result = mysqli_query($conn, $leave_query);
                
                if (mysqli_num_rows($leave_result) > 0): ?>
                    <div class="space-y-3">
                        <?php while ($leave = mysqli_fetch_assoc($leave_result)): ?>
                            <div class="flex items-center justify-between p-3 border border-red-200 rounded-lg bg-red-50">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center">
                                        <span class="text-sm font-bold text-white">
                                            <?php echo strtoupper(substr($leave['first_name'], 0, 1) . substr($leave['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($leave['department']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($leave['position']); ?></p>
                                        <p class="text-sm text-red-700 mt-1">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <?php echo htmlspecialchars($leave['reason']); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-1"></i>
                                        On Leave
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-check text-green-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">No teachers are on consultation leave today.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="mt-8 flex justify-center space-x-4">
            <a href="student-screen.php" class="bg-seait-orange text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition-colors">
                <i class="fas fa-users mr-2"></i>
                View Student Screen
            </a>
            <a href="teacher-screen.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-chalkboard-teacher mr-2"></i>
                View Teacher Screen
            </a>
            <a href="index.php" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-home mr-2"></i>
                Back to Consultation Home
            </a>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h3 class="text-lg font-semibold mb-2">Consultation Leave Test System</h3>
                <p class="text-gray-400">Testing the integration of consultation leave functionality</p>
                <div class="mt-4 flex justify-center space-x-6 text-sm text-gray-400">
                    <span class="flex items-center">
                        <i class="fas fa-check-circle text-green-400 mr-1"></i>
                        Available Teachers
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-times-circle text-red-400 mr-1"></i>
                        Teachers on Leave
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-clock text-blue-400 mr-1"></i>
                        Consultation Hours
                    </span>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
