<?php
session_start();

// Set timezone BEFORE including database.php to ensure it's applied globally
if (function_exists('date_default_timezone_set')) {
    // Try to detect the system timezone automatically
    $system_timezone = null;
    
    // Method 1: Try to get timezone from system
    if (function_exists('shell_exec')) {
        $system_timezone = trim(shell_exec('date +%Z'));
        if ($system_timezone === 'PST' || $system_timezone === 'PDT') {
            date_default_timezone_set('Asia/Manila');
        }
    }
    
    // Method 2: If system detection fails, use Philippines time
    if (!date_default_timezone_get() || date_default_timezone_get() === 'UTC') {
        date_default_timezone_set('Asia/Manila');
    }
    
    // Method 3: Force Philippines time as fallback
    date_default_timezone_set('Asia/Manila');
}

require_once '../config/database.php';

// Double-check timezone is set correctly
if (date_default_timezone_get() !== 'Asia/Manila') {
    date_default_timezone_set('Asia/Manila');
}

// Debug: Log timezone and current time for verification
error_log("Student Screen - Timezone: " . date_default_timezone_get() . ", Current Date: " . date('Y-m-d H:i:s l'));

// Helper function to get correct date/time display
function getCorrectDateTime($format) {
    // Try to get system time if PHP time seems wrong
    if (function_exists('shell_exec')) {
        $system_day = trim(shell_exec('date +%A'));
        $system_time = trim(shell_exec('date +%H:%M:%S'));
        $system_date = trim(shell_exec('date +%Y-%m-%d'));
        
        // Check if there's a significant time difference (more than 1 hour)
        $php_timestamp = strtotime(date('Y-m-d H:i:s'));
        $system_timestamp = strtotime($system_date . ' ' . $system_time);
        $time_diff = abs($php_timestamp - $system_timestamp);
        
        if ($time_diff > 3600) { // More than 1 hour difference
            // Use system time for display
            $system_datetime = $system_date . ' ' . $system_time;
            return date($format, strtotime($system_datetime));
        }
    }
    
    // Use PHP time if no significant difference
    return date($format);
}

// Set page title
$page_title = 'Student Screen - Available Teachers';

// Get selected department from URL parameter
$selected_department = $_GET['dept'] ?? '';

// First, let's check what departments are actually in the faculty table
$check_departments_query = "SELECT DISTINCT department FROM faculty WHERE is_active = 1 ORDER BY department";
$check_departments_result = mysqli_query($conn, $check_departments_query);
$available_departments = [];
while ($row = mysqli_fetch_assoc($check_departments_result)) {
    $available_departments[] = $row['department'];
}

// Get current day of week and active semester - Use system time as fallback
$current_day = date('l'); // Returns Monday, Tuesday, etc.
$current_time = date('H:i:s'); // Current time in HH:MM:SS format

// If PHP time seems wrong (more than 1 hour difference from system), use system time
if (function_exists('shell_exec')) {
    $system_day = trim(shell_exec('date +%A'));
    $system_time = trim(shell_exec('date +%H:%M:%S'));
    $system_date = trim(shell_exec('date +%Y-%m-%d'));
    
    // Check if there's a significant time difference (more than 1 hour)
    $php_timestamp = strtotime(date('Y-m-d H:i:s'));
    $system_timestamp = strtotime($system_date . ' ' . $system_time);
    $time_diff = abs($php_timestamp - $system_timestamp);
    
    if ($time_diff > 3600) { // More than 1 hour difference
        error_log("Time difference detected: PHP=" . date('Y-m-d H:i:s') . ", System=" . $system_date . ' ' . $system_time);
        $current_day = $system_day;
        $current_time = $system_time;
    }
}

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

// Get teachers available for consultation in the selected department who have scanned their QR code
// Teachers are available regardless of their scheduled consultation hours once they scan
$teachers_query = "SELECT 
                    f.id,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position,
                    f.email,
                    f.bio,
                    f.image_url,
                    f.is_active,
                    COALESCE(MIN(ch.start_time), '08:00:00') as start_time,
                    COALESCE(MAX(ch.end_time), '17:00:00') as end_time,
                    COALESCE(GROUP_CONCAT(DISTINCT ch.room ORDER BY ch.room SEPARATOR ', '), 'Available') as room,
                    COALESCE(GROUP_CONCAT(DISTINCT ch.notes ORDER BY ch.notes SEPARATOR '; '), 'Available for consultation') as notes,
                    ta.scan_time,
                    ta.last_activity
                   FROM faculty f 
                   INNER JOIN teacher_availability ta ON f.id = ta.teacher_id
                   LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                       AND ch.day_of_week = ? 
                       AND ch.is_active = 1
                       " . ($active_semester ? "AND ch.semester = ?" : "") . "
                       " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                   WHERE f.is_active = 1 
                   AND f.department = ?
                   AND f.id NOT IN (
                       SELECT teacher_id 
                       FROM consultation_leave 
                       WHERE leave_date = CURDATE()
                   )
                   AND ta.availability_date = CURDATE()
                   AND ta.status = 'available'
                   GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.last_activity
                   ORDER BY ta.scan_time DESC, f.first_name, f.last_name";

$teachers_stmt = mysqli_prepare($conn, $teachers_query);
if ($teachers_stmt) {
    // Build parameter types and values dynamically
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
    
    // Add department parameter at the end
    $param_types .= "s";
    $param_values[] = $selected_department;
    
    mysqli_stmt_bind_param($teachers_stmt, $param_types, ...$param_values);
    mysqli_stmt_execute($teachers_stmt);
    $teachers_result = mysqli_stmt_get_result($teachers_stmt);
    
    $teachers = [];
    while ($row = mysqli_fetch_assoc($teachers_result)) {
        $teachers[] = $row;
    }
    
    // Teachers found successfully
} else {
    $teachers = [];
}

// If no teachers found for the department, try partial matching
if (empty($teachers)) {
    
    // Try partial matching for teachers who have scanned their QR code
    // Teachers are available regardless of their scheduled consultation hours once they scan
    $partial_query = "SELECT 
                        f.id,
                        f.first_name,
                        f.last_name,
                        f.department,
                        f.position,
                        f.email,
                        f.bio,
                        f.image_url,
                        f.is_active,
                        COALESCE(MIN(ch.start_time), '08:00:00') as start_time,
                        COALESCE(MAX(ch.end_time), '17:00:00') as end_time,
                        COALESCE(GROUP_CONCAT(DISTINCT ch.room ORDER BY ch.room SEPARATOR ', '), 'Available') as room,
                        COALESCE(GROUP_CONCAT(DISTINCT ch.notes ORDER BY ch.notes SEPARATOR '; '), 'Available for consultation') as notes,
                        ta.scan_time,
                        ta.last_activity
                       FROM faculty f 
                       INNER JOIN teacher_availability ta ON f.id = ta.teacher_id
                       LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                           AND ch.day_of_week = ? 
                           AND ch.is_active = 1
                           " . ($active_semester ? "AND ch.semester = ?" : "") . "
                           " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                       WHERE f.is_active = 1 
                       AND f.department LIKE ?
                       AND f.id NOT IN (
                           SELECT teacher_id 
                           FROM consultation_leave 
                           WHERE leave_date = CURDATE()
                       )
                       AND ta.availability_date = CURDATE()
                       AND ta.status = 'available'
                       GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.last_activity
                       ORDER BY ta.scan_time DESC, f.first_name, f.last_name";
    
    $partial_stmt = mysqli_prepare($conn, $partial_query);
    if ($partial_stmt) {
        $search_term = '%' . $selected_department . '%';
        
        // Build parameter types and values dynamically
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
        
        // Add department search term at the end
        $param_types .= "s";
        $param_values[] = $search_term;
        
        mysqli_stmt_bind_param($partial_stmt, $param_types, ...$param_values);
        mysqli_stmt_execute($partial_stmt);
        $partial_result = mysqli_stmt_get_result($partial_stmt);
        
        while ($row = mysqli_fetch_assoc($partial_result)) {
            $teachers[] = $row;
        }
    }
}

// If still no teachers found and no specific department was selected, get all available teachers
if (empty($teachers) && empty($selected_department)) {
    
    $fallback_query = "SELECT 
                        f.id,
                        f.first_name,
                        f.last_name,
                        f.department,
                        f.position,
                        f.email,
                        f.bio,
                        f.image_url,
                        f.is_active,
                        COALESCE(MIN(ch.start_time), '08:00:00') as start_time,
                        COALESCE(MAX(ch.end_time), '17:00:00') as end_time,
                        COALESCE(GROUP_CONCAT(DISTINCT ch.room ORDER BY ch.room SEPARATOR ', '), 'Available') as room,
                        COALESCE(GROUP_CONCAT(DISTINCT ch.notes ORDER BY ch.notes SEPARATOR '; '), 'Available for consultation') as notes,
                        ta.scan_time,
                        ta.last_activity
                       FROM faculty f 
                       INNER JOIN teacher_availability ta ON f.id = ta.teacher_id
                       LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                           AND ch.day_of_week = ? 
                           AND ch.is_active = 1
                           " . ($active_semester ? "AND ch.semester = ?" : "") . "
                           " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                       WHERE f.is_active = 1 
                       AND f.id NOT IN (
                           SELECT teacher_id 
                           FROM consultation_leave 
                           WHERE leave_date = CURDATE()
                       )
                       AND ta.availability_date = CURDATE()
                       AND ta.status = 'available'
                       GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.last_activity
                       ORDER BY ta.scan_time DESC, f.department, f.first_name, f.last_name";
    
    $fallback_stmt = mysqli_prepare($conn, $fallback_query);
    if ($fallback_stmt) {
        // Build parameter types and values dynamically
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
        
        mysqli_stmt_bind_param($fallback_stmt, $param_types, ...$param_values);
        mysqli_stmt_execute($fallback_stmt);
        $fallback_result = mysqli_stmt_get_result($fallback_stmt);
        
        while ($row = mysqli_fetch_assoc($fallback_result)) {
            $teachers[] = $row;
        }
    }
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
    <link rel="stylesheet" href="fab-styles.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        /* Canvas Background Animation */
        #canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
            pointer-events: none;
        }

        /* Standby Mode Background Video Styles */
        .standby-video-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }
        
        .standby-video-container.active {
            display: flex;
            opacity: 1;
        }
        
        .standby-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        
        .standby-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .standby-text {
            background: rgba(0, 0, 0, 0.8);
            padding: 20px 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .standby-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 10000;
            display: none;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        
        .standby-indicator.active {
            display: block;
            opacity: 1;
        }
        
        .standby-countdown {
            position: fixed;
            top: 60px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            z-index: 10000;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .standby-countdown.active {
            display: block;
            opacity: 1;
        }
        
        .fullscreen-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10001;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .fullscreen-btn:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .fullscreen-btn:active {
            transform: scale(0.95);
        }
        
        .fullscreen-btn i {
            font-size: 18px;
            transition: all 0.3s ease;
        }
        
        .fullscreen-btn.fullscreen i {
            transform: rotate(180deg);
        }
        
        /* Fullscreen button always visible on student screen */
        .fullscreen-btn {
            opacity: 1;
            pointer-events: auto;
            transform: scale(1);
        }
        
        /* Enhanced Teacher Card Styling with Animations */
        .teacher-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
        }
        
        /* Card hover animations */
        .teacher-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15), 0 8px 16px -4px rgba(0, 0, 0, 0.1);
            border-color: #FF6B35;
        }
        

        


        .teacher-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }
        
        /* Enhanced focus styles for student ID input */
        #studentIdInput:focus {
            outline: none;
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1), 0 0 0 1px rgba(16, 185, 129, 0.2);
            transform: scale(1.02);
            transition: all 0.2s ease;
        }
        

        
        #studentIdInput {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        #studentIdInput:focus {
            animation: inputGlow 2s ease-in-out infinite;
        }
        
        @keyframes inputGlow {
            0%, 100% {
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1), 0 0 0 1px rgba(16, 185, 129, 0.2);
            }
            50% {
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2), 0 0 0 1px rgba(16, 185, 129, 0.4);
            }
        }
        
        /* Pulsing animation for focused input */
        #studentIdInput:focus {
            animation: inputPulse 2s infinite;
        }
        
        @keyframes inputPulse {
            0%, 100% {
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1), 0 0 0 1px rgba(16, 185, 129, 0.2);
            }
            50% {
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2), 0 0 0 1px rgba(16, 185, 129, 0.4);
            }
        }

        .teacher-card:hover::before {
            left: 100%;
        }

        .teacher-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15), 0 8px 16px -4px rgba(0, 0, 0, 0.1);
            border-color: #FF6B35;
        }
        
        /* Clickable state for teacher cards */
        .teacher-card.clickable {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .teacher-card.clickable:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15), 0 8px 16px -4px rgba(0, 0, 0, 0.1);
            border-color: #FF6B35;
        }
        
        .teacher-card.clickable:active {
            transform: translateY(-4px) scale(1.01);
        }

        .teacher-card:active {
            transform: translateY(-4px) scale(1.01);
        }

        /* Enhanced Status Indicators */
        .status-online {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
        }

        .status-busy {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
        }

        .status-offline {
            background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%);
            box-shadow: 0 0 0 4px rgba(107, 114, 128, 0.2);
        }

        /* Enhanced Loading States */
        .loading {
            display: none;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .loading.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Enhanced Animations */
        @keyframes slideInUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        @keyframes shake {
            0%, 100% {
                transform: translateX(0);
            }
            10%, 30%, 50%, 70%, 90% {
                transform: translateX(-5px);
            }
            20%, 40%, 60%, 80% {
                transform: translateX(5px);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes glow {
            0%, 100% {
                box-shadow: 0 0 5px rgba(255, 107, 53, 0.5);
            }
            50% {
                box-shadow: 0 0 20px rgba(255, 107, 53, 0.8), 0 0 30px rgba(255, 107, 53, 0.6);
            }
        }
        
        .notification-enter {
            animation: slideInUp 0.5s ease-out;
        }
        
        .notification-pulse {
            animation: pulse 2s infinite;
        }
        
        .notification-shake {
            animation: shake 0.5s ease-in-out;
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        .glow-animation {
            animation: glow 2s ease-in-out infinite;
        }

        /* Enhanced Header Card Animations */
        .enhanced-header-card {
            background: linear-gradient(135deg, #fff8f0 0%, #ffffff 100%);
            border: 1px solid #fed7aa;
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .enhanced-header-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px -12px rgba(255, 107, 53, 0.15);
        }

        /* Prevent content overflow */
        .enhanced-header-content {
            overflow: hidden;
        }

        .enhanced-header-text {
            overflow: hidden;
        }

        .enhanced-header-text h2 {
            word-break: break-word;
            hyphens: auto;
        }

        .info-grid-item {
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #ffffff 0%, #fefefe 100%);
        }

        .info-grid-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgba(255, 107, 53, 0.1);
            border-color: #fb923c;
        }

        .time-display-card {
            background: linear-gradient(135deg, #ff6b35 0%, #ea580c 100%);
            box-shadow: 0 10px 25px rgba(255, 107, 53, 0.3);
            transition: all 0.3s ease;
        }

        .time-display-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(255, 107, 53, 0.4);
        }

        .live-indicator {
            animation: pulse 2s ease-in-out infinite;
        }

        .live-time {
            transition: all 0.3s ease;
        }

        .live-time:hover {
            transform: scale(1.02);
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.7;
                transform: scale(1.1);
            }
        }

        @keyframes timeUpdate {
            0% {
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
            100% {
                opacity: 1;
            }
        }

        .time-updating {
            animation: timeUpdate 1s ease-in-out;
        }


        
        /* Ultra Simple Modal - No Effects At All */
        .ultra-simple-modal {
            background: rgba(0, 0, 0, 0.5);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .ultra-simple-content {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            max-width: 720px;
            width: 95%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        /* Nuclear option - disable ALL effects */
        * {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            filter: none !important;
        }

        body, html {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            filter: none !important;
        }

        /* Enhanced Department Filter Buttons */
        .department-filter-btn {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .department-filter-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .department-filter-btn:hover::before {
            left: 100%;
        }

        .department-filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        /* Enhanced Header */
        .header-gradient {
            background: linear-gradient(135deg, #FF6B35 0%, #E55A2B 100%);
        }

        /* Enhanced Cards */
        .enhanced-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .enhanced-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
            border-color: #FF6B35;
        }

        /* Enhanced Avatar */
        .teacher-avatar {
            position: relative;
            transition: all 0.3s ease;
        }

        .teacher-avatar::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, #FF6B35, #E55A2B);
            border-radius: 50%;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .teacher-card:hover .teacher-avatar::after {
            opacity: 1;
        }

        .teacher-card:hover .teacher-avatar {
            transform: scale(1.1);
        }

        /* Enhanced Status Badge */
        .status-badge {
            position: relative;
            animation: pulse 2s infinite;
        }

        .status-badge::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            background: inherit;
            border-radius: inherit;
            opacity: 0.3;
            animation: pulse 2s infinite;
        }

        /* Enhanced Footer */
        .footer-gradient {
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
        }

        /* Responsive Enhancements */
        @media (max-width: 768px) {
            .teacher-card:hover {
                transform: translateY(-4px) scale(1.01);
            }
            
            .department-filter-btn:hover {
                transform: translateY(-1px);
            }

            /* Mobile Header Adjustments */
            .header-gradient {
                padding: 0.5rem 0;
            }

            .header-gradient h1 {
                font-size: 1.25rem;
                line-height: 1.5;
            }

            .header-gradient img {
                height: 2.5rem;
                width: auto;
            }

            /* Mobile Main Content */
            .main-content {
                padding: 1rem;
            }

            /* Mobile Page Header Card */
            .enhanced-card {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .enhanced-card h2 {
                font-size: 1.5rem;
            }

            .enhanced-card .flex {
                flex-direction: column;
                gap: 1rem;
            }

            .enhanced-card .text-right {
                text-align: center;
            }

            /* Mobile Department Filter */
            .department-filter-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .department-filter-btn {
                padding: 0.75rem 0.5rem;
                font-size: 0.875rem;
            }

            /* Mobile Teacher Cards */
            .teacher-card {
                margin-bottom: 1rem;
            }

            .teacher-card .p-8 {
                padding: 1.5rem;
            }

            .teacher-card .space-x-6 {
                gap: 1rem;
            }

            .teacher-card .w-20 {
                width: 4rem;
                height: 4rem;
            }

            .teacher-card .text-xl {
                font-size: 1.125rem;
            }

            .teacher-card .text-2xl {
                font-size: 1.5rem;
            }

            /* Mobile Footer */
            .footer-gradient {
                padding: 2rem 1rem;
            }

            .footer-gradient .space-x-6 {
                flex-direction: column;
                gap: 1rem;
            }

            /* Mobile Loading State */
            .loading .max-w-sm {
                max-width: 90%;
                margin: 0 1rem;
            }

            /* Mobile Pending Request Indicator */
            #pendingRequestIndicator {
                bottom: 1rem;
                right: 1rem;
                left: 1rem;
                max-width: none;
            }

                    /* Mobile Grid Adjustments */
        .grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        /* Enhanced Header Card Responsive Design - Compact */
        .enhanced-header-layout {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .enhanced-header-content {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .enhanced-header-main {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .enhanced-header-text {
            flex: 1;
            min-width: 0;
        }

        .enhanced-header-text h2 {
            margin-bottom: 0.25rem;
        }

        .enhanced-header-text p {
            margin-bottom: 0.75rem;
        }

        .enhanced-header-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
        }

        /* Desktop Layout */
        @media (min-width: 1024px) {
            .enhanced-header-layout {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 2rem;
                max-width: 100%;
            }

            .enhanced-header-content {
                flex: 1;
                max-width: calc(100% - 280px); /* Reduced space for time card */
            }

            .enhanced-header-info-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Large Desktop Layout */
        @media (min-width: 1280px) {
            .enhanced-header-layout {
                gap: 2.5rem;
            }

            .enhanced-header-content {
                max-width: calc(100% - 300px);
            }
        }

        /* Tablet Layout */
        @media (min-width: 768px) and (max-width: 1023px) {
            .enhanced-header-main {
                gap: 1.5rem;
            }

            .enhanced-header-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Mobile Layout */
        @media (max-width: 767px) {
            .enhanced-header-card {
                padding: 1rem !important;
            }

            .enhanced-header-main {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 1rem;
            }

            .enhanced-header-text h2 {
                font-size: 1.5rem !important;
                line-height: 1.3;
            }

            .enhanced-header-text p {
                font-size: 0.875rem !important;
            }

            .enhanced-header-info-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .info-grid-item {
                padding: 0.75rem !important;
                text-align: left;
            }

            .time-display-card {
                padding: 1.5rem !important;
                margin-top: 1rem;
            }

            .time-display-card .text-xl {
                font-size: 1.25rem !important;
            }

            .time-display-card .text-2xl {
                font-size: 1.5rem !important;
            }

            .time-display-card .text-3xl {
                font-size: 1.75rem !important;
            }

            /* Hide live indicator on mobile to save space */
            .live-indicator-mobile-hide {
                display: none;
            }
        }

        /* Extra small mobile */
        @media (max-width: 480px) {
            .enhanced-header-card {
                margin-left: -0.5rem;
                margin-right: -0.5rem;
                border-radius: 0.5rem;
            }

            .enhanced-header-text h2 {
                font-size: 1.25rem !important;
            }

            .enhanced-header-info-grid {
                margin-top: 1rem;
            }
        }

            /* Mobile Modal Adjustments */
            .modal-content {
                margin: 1rem;
                max-width: none;
            }

            /* Mobile Text Adjustments */
            .text-3xl {
                font-size: 1.5rem;
            }

            .text-2xl {
                font-size: 1.25rem;
            }

            .text-xl {
                font-size: 1.125rem;
            }

            /* Mobile Spacing Adjustments */
            .space-x-4 > * + * {
                margin-left: 0.5rem;
            }

            .space-x-6 > * + * {
                margin-left: 0.75rem;
            }

            .space-x-8 > * + * {
                margin-left: 1rem;
            }
        }

        @media (max-width: 480px) {
            /* Extra Small Mobile Adjustments */
            .header-gradient h1 {
                font-size: 1.125rem;
            }

            .enhanced-card {
                padding: 1rem;
            }

            .enhanced-card h2 {
                font-size: 1.25rem;
            }

            .teacher-card .p-8 {
                padding: 1rem;
            }

            .teacher-card .w-20 {
                width: 3rem;
                height: 3rem;
            }

            .teacher-card .text-xl {
                font-size: 1rem;
            }

            .department-filter-grid {
                grid-template-columns: 1fr;
            }

            .department-filter-btn {
                padding: 1rem 0.75rem;
                font-size: 0.875rem;
            }

            /* Mobile Navigation */
            .mobile-nav {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            /* Mobile Back Button */
            .mobile-back-btn {
                width: 100%;
                justify-content: center;
                margin-top: 0.5rem;
            }
        }

        /* Enhanced Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #FF6B35, #E55A2B);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #E55A2B, #D4491B);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Canvas Background Animation -->
    <canvas id="canvas"></canvas>
    <!-- Standby Mode Background Video -->
    <div id="standbyVideoContainer" class="standby-video-container">
        <video id="standbyVideo" class="standby-video" autoplay muted loop>
            <source src="background-video/developers.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
    
    <!-- Standby Mode Indicator -->
    <div id="standbyIndicator" class="standby-indicator">
        <i class="fas fa-video mr-2"></i>Standby Mode
    </div>
    
    <!-- Standby Countdown Indicator -->
    <div id="standbyCountdown" class="standby-countdown">
                                    <i class="fas fa-clock mr-1"></i>Standby in <span id="countdownTimer">30</span>s
    </div>
    
    <!-- No Teachers Available Modal -->
    <div id="noTeachersModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-3xl shadow-2xl border-4 border-orange-500 p-8 max-w-md w-full transform scale-95 opacity-0 transition-all duration-300">
                <!-- Modal Header -->
                <div class="text-center mb-6">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 shadow-lg mb-4 mx-auto flex items-center justify-center">
                        <i class="fas fa-user-slash text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">No Teachers Available</h3>
                    <div class="w-24 h-1 bg-gradient-to-r from-orange-500 to-orange-600 rounded-full mx-auto"></div>
                </div>
                
                <!-- Modal Content -->
                <div class="text-center mb-8">
                    <p class="text-gray-700 mb-4">
                        <i class="fas fa-info-circle text-orange-500 mr-2"></i>
                        No teachers are currently available for consultation at this time.
                    </p>
                    <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-4">
                        <p class="text-orange-800 text-sm">
                            <i class="fas fa-clock mr-2"></i>
                            Teachers only appear when they have scheduled consultation hours for the current day and time.
                        </p>
                    </div>
                    <p class="text-sm text-gray-600">
                        Please try again later or check with your department for available consultation schedules.
                    </p>
                </div>
                
                <!-- Modal Buttons -->
                <div class="flex space-x-3">
                    <button onclick="closeNoTeachersModal()" class="flex-1 bg-gradient-to-r from-gray-400 to-gray-500 text-white px-6 py-3 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-xl hover:shadow-2xl font-bold text-lg border-2 border-gray-300 hover:border-gray-400">
                        <i class="fas fa-times mr-2"></i>Close
                    </button>
                    <button onclick="refreshTeachersList()" class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 text-white px-6 py-3 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-xl hover:shadow-2xl font-bold text-lg border-2 border-orange-400 hover:border-orange-500">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fullscreen Button -->
    <button id="fullscreenBtn" class="fullscreen-btn" title="Toggle Fullscreen">
        <i class="fas fa-expand"></i>
    </button>


    <!-- Main Content -->
    <main class="flex-1 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 main-content">
        <!-- Error Message -->
        <?php if (isset($_GET['error']) && $_GET['error'] === 'request_not_accepted'): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong class="font-bold">Consultation Request Not Accepted</strong>
            </div>
            <p class="mt-1">The teacher has not accepted your consultation request yet. Please wait for their response or try selecting another teacher.</p>
        </div>
        <?php endif; ?>

                <!-- Enhanced Page Header Card -->
        <div class="enhanced-card enhanced-header-card rounded-xl shadow-lg p-4 sm:p-6 mb-4 sm:mb-6 border-l-4 border-orange-500">
            <div class="enhanced-header-layout">
                <!-- Left Section: Main Content -->
               
                
                <!-- Right Section: Enhanced Time Display -->
                <div class="flex-shrink-0 w-full lg:w-auto lg:min-w-[240px]">
                    <div class="time-display-card rounded-xl p-3 sm:p-4 text-white shadow-lg border border-orange-400">
                        <div class="text-center">
                            <!-- Date Display -->
                            <div class="mb-2">
                                <p class="text-xs text-orange-100 mb-1 font-medium">Today's Date</p>
                                <p class="text-sm sm:text-base font-bold text-white">
                                    <?php echo getCorrectDateTime('l, F j'); ?>
                                </p>
                            </div>
                            
                            <!-- Time Display -->
                            <div class="mb-2">
                                <p class="text-xs text-orange-100 mb-1 font-medium flex items-center justify-center">
                                    <span>Current Time (<?php echo date_default_timezone_get(); ?>)</span>
                                </p>
                                <p id="liveTime" class="text-lg sm:text-xl lg:text-2xl font-bold text-white live-time">
                                    <?php echo getCorrectDateTime('g:i:s A'); ?>
                                </p>
                            </div>
                            
                            <!-- Status Indicator -->
                            <div class="flex items-center justify-center space-x-2">
                                <div class="w-2 h-2 bg-green-400 rounded-full live-indicator"></div>
                                <span class="text-xs font-medium text-orange-100">Live Clock</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student ID QR Scanner Section -->
        <div class="enhanced-card rounded-xl shadow-lg p-3 sm:p-4 mb-3 sm:mb-4 border-l-4 border-green-500">
            <div class="flex items-center mb-3">
                <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                    <i class="fas fa-qrcode text-white text-sm"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-800">🔑 QR Code Scanner - Teachers & Students</h3>
                </div>
                <!-- Real-time Update Indicator -->
                <div id="realtimeIndicator" class="hidden items-center space-x-2 text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full border border-green-200 animate-pulse">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="font-medium">Live Updates</span>
                </div>
            </div>
            
            <!-- QR Scanner Input -->
            <div class="bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-lg p-3">
                <div class="flex items-center space-x-3">
                    <div class="flex-1">
                        <label for="studentIdInput" class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-qrcode mr-1 text-green-600"></i>QR Code Scanner (Teacher/Student)
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   id="studentIdInput" 
                                   name="student_id" 
                                   placeholder="🔍 Scan Teacher QR (to mark available) or Student QR (to request consultation)" 
                                   class="w-full px-3 py-2 border border-green-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-base text-center pr-10"
                                   autocomplete="off"
                                   inputmode="text"
                                   autofocus
                                   required>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2">
                                <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Teacher QR → Mark available & accept requests | Student QR → Start consultation request
                        </p>
                    </div>
                </div>
                
                <!-- Student Info Display -->
                <div id="studentInfoDisplay" class="mt-3 p-3 bg-white rounded-lg border border-green-200 hidden">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-white text-sm"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-sm" id="studentNameDisplay">Student Name</h4>
                            <p class="text-xs text-gray-600" id="studentIdDisplay">Student ID</p>
                            <p class="text-xs text-gray-500" id="studentDeptDisplay">Department</p>
                        </div>
                        <div class="ml-auto">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Filter -->
        <?php if (empty($selected_department)): ?>
        <div class="enhanced-card rounded-xl shadow-lg p-4 sm:p-8 mb-6 sm:mb-8 border-l-4 border-blue-500">
            <div class="flex flex-col sm:flex-row items-start sm:items-center mb-6 space-y-4 sm:space-y-0">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center mr-0 sm:mr-4 flex-shrink-0">
                    <i class="fas fa-filter text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-800">Select Your Department</h3>
                    <p class="text-gray-600 text-sm sm:text-base">Choose your department to see teachers with scheduled consultation hours for today and ensure proper consultation matching.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 department-filter-grid">
                <?php
                // Get actual departments from database
                $dept_query = "SELECT DISTINCT department FROM faculty WHERE is_active = 1 ORDER BY department";
                $dept_result = mysqli_query($conn, $dept_query);
                $departments = [];
                while ($row = mysqli_fetch_assoc($dept_result)) {
                    $departments[] = $row['department'];
                }
                
                // Add some common student departments that might not have teachers yet
                $all_departments = array_merge($departments, [
                    'Computer Science',
                    'Mathematics',
                    'English',
                    'History',
                    'General'
                ]);
                $all_departments = array_unique($all_departments);
                sort($all_departments);
                
                // Add "Show All Teachers" option at the beginning
                ?>
                <button class="department-filter-btn bg-gradient-to-r from-orange-500 to-orange-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-xl text-xs sm:text-sm font-medium shadow-lg hover:from-orange-600 hover:to-orange-700 transform hover:scale-105 transition-all duration-300" 
                        data-dept="">
                    <i class="fas fa-users mr-1 sm:mr-2"></i>
                    <span class="hidden sm:inline">Show All Available Today</span>
                    <span class="sm:hidden">All Available</span>
                </button>
                <?php
                
                foreach ($all_departments as $dept):
                    $display_name = $dept;
                    if (strlen($dept) > 15) {
                        $display_name = substr($dept, 0, 12) . '...';
                    }
                ?>
                <button class="department-filter-btn bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 px-4 sm:px-6 py-2 sm:py-3 rounded-xl text-xs sm:text-sm font-medium shadow-md hover:from-gray-200 hover:to-gray-300 transform hover:scale-105 transition-all duration-300 border border-gray-300" 
                        data-dept="<?php echo htmlspecialchars($dept); ?>"
                        title="<?php echo htmlspecialchars($dept); ?>">
                    <i class="fas fa-building mr-1 sm:mr-2"></i>
                    <?php echo htmlspecialchars($display_name); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- QR Code Required Notice -->
        <div id="studentIdRequiredNotice" class="col-span-full bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center mb-6">
            <div class="flex items-center justify-center space-x-3 mb-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-qrcode text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-yellow-800">QR Code Required</h3>
                    <p class="text-yellow-700">Please scan a QR code above to proceed</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="flex items-center justify-center space-x-2 text-yellow-600 bg-yellow-100 rounded-lg p-3">
                    <i class="fas fa-user-tie"></i>
                    <span><strong>Teacher QR:</strong> Mark as available</span>
                </div>
                <div class="flex items-center justify-center space-x-2 text-yellow-600 bg-yellow-100 rounded-lg p-3">
                    <i class="fas fa-user-graduate"></i>
                    <span><strong>Student QR:</strong> Request consultation</span>
                </div>
            </div>
        </div>



        <!-- Teachers Section (Hidden until student ID is scanned) -->
        <div id="teachersSection" class="hidden">
            <!-- Teachers Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                <?php if (empty($teachers)): ?>
                    <div class="col-span-full bg-white rounded-lg shadow-md p-8 text-center">
                        <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Teachers Available Today</h3>
                        <p class="text-gray-500 mb-4">
                            <?php if (!empty($selected_department)): ?>
                                No teachers from <strong><?php echo htmlspecialchars($selected_department); ?></strong> have scheduled consultation hours for today (<?php echo getCorrectDateTime('l, F j, Y'); ?>).
                            <?php else: ?>
                                No teachers have scheduled consultation hours for today (<?php echo getCorrectDateTime('l, F j, Y'); ?>).
                            <?php endif; ?>
                        </p>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                            <p class="text-yellow-800 text-sm">
                                <i class="fas fa-info-circle mr-2"></i>
                                Teachers only appear when they have scheduled consultation hours for the current day and time.
                            </p>
                        </div>
                        <?php if (empty($selected_department)): ?>
                            <a href="index.php" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-seait-dark transition-colors">
                                Try Different Department
                            </a>
                        <?php else: ?>
                            <a href="student-screen.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>Back to All Departments
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($teachers as $teacher): ?>
                        <div class="teacher-card bg-white rounded-xl shadow-lg hover:shadow-2xl cursor-pointer transform hover:scale-105 transition-all duration-300 border border-gray-200 flex flex-col overflow-hidden" 
                             data-teacher-id="<?php echo htmlspecialchars($teacher['id']); ?>"
                             data-teacher-name="<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>"
                             data-teacher-dept="<?php echo htmlspecialchars($teacher['department']); ?>">
                            
                            <!-- Teacher Avatar at Top -->
                            <div class="p-6 flex justify-center bg-gradient-to-br from-gray-50 to-white border-b border-gray-100">
                                <div class="teacher-avatar">
                                    <?php if ($teacher['image_url']): ?>
                                        <img src="../<?php echo htmlspecialchars($teacher['image_url']); ?>" 
                                             alt="Teacher" class="w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover border-4 border-orange-500 shadow-lg">
                                    <?php else: ?>
                                        <?php
                                        // Generate initials from first and last name
                                        $first_initial = strtoupper(substr($teacher['first_name'], 0, 1));
                                        $last_initial = strtoupper(substr($teacher['last_name'], 0, 1));
                                        $initials = $first_initial . $last_initial;
                                        ?>
                                        <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center border-4 border-orange-500 shadow-lg">
                                            <span class="text-white text-xl sm:text-2xl font-bold"><?php echo htmlspecialchars($initials); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Teacher Information - Centered -->
                            <div class="p-4 sm:p-6 flex-1 flex flex-col justify-center">
                                <div class="text-center space-y-3">
                                    <!-- Teacher Name -->
                                    <h3 class="text-lg sm:text-xl font-bold text-gray-800">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </h3>
                                                                                                        </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="px-4 sm:px-6 py-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-b-xl">
                                <div class="flex items-center justify-center">
                                    <div class="text-center">
                                        <div class="text-xs sm:text-sm text-orange-700 font-medium mb-2">Tap to start consultation</div>
                                        <div class="flex items-center justify-center space-x-1 sm:space-x-2">
                                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse"></div>
                                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

            <!-- QR Scanner Modal -->
    <div id="qrScannerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">QR Code Scanner</h3>
                    <button onclick="closeQRScannerModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="qrScannerContainer" class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <i class="fas fa-camera text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600">Camera access required for QR scanning</p>
                    </div>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-500">Position the QR code within the camera view</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div id="floatingActionButton" class="fixed bottom-6 right-6 z-40">
        <button id="fabButton" class="fab-button">
            <i class="fas fa-users"></i>
        </button>
    </div>

    <!-- Floating Action Modal -->
    <div id="fabModal" class="fab-modal">
            <div class="fab-modal-overlay" id="fabModalOverlay"></div>
            <div class="fab-modal-content">
                <div class="fab-modal-header">
                    <h3 id="fabModalTitle">Available Teachers</h3>
                    <button id="fabModalClose" class="fab-modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="fab-modal-body">
                    <div id="fabTeachersGrid" class="fab-teachers-grid">
                        <!-- Teachers will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Loading State -->
        <div id="loadingState" class="loading fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 backdrop-blur-sm" style="display: none;">
            <div class="bg-white rounded-2xl p-8 flex flex-col items-center space-y-6 shadow-2xl border border-gray-200 max-w-sm w-full mx-4">
                <div class="relative">
                    <div class="w-16 h-16 border-4 border-orange-200 border-t-orange-500 rounded-full animate-spin"></div>
                    <div class="absolute inset-0 w-16 h-16 border-4 border-transparent border-t-orange-600 rounded-full animate-spin" style="animation-duration: 1.5s; animation-direction: reverse;"></div>
                </div>
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Notifying Teacher</h3>
                    <p class="text-gray-600 text-sm">Please wait while we send your consultation request...</p>
                </div>
                <div class="flex space-x-2">
                    <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></div>
                    <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                    <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                </div>
            </div>
        </div>

        <!-- Notifications removed for cleaner interface -->
    </main>

    <!-- Footer -->
    <footer class="footer-gradient text-white py-8 sm:py-12 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="flex flex-col sm:flex-row items-center justify-center mb-4 space-y-2 sm:space-y-0">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-6 sm:h-8 w-auto mr-0 sm:mr-3">
                    <h3 class="text-lg sm:text-xl font-bold">SEAIT Consultation Portal</h3>
                </div>
                <p class="text-gray-300 mb-2 text-sm sm:text-base">&copy; <?php echo date('Y'); ?> SEAIT. All rights reserved.</p>
                <p class="text-gray-400 text-xs sm:text-sm">Student Consultation Portal - Connecting Students with Teachers</p>
                <div class="flex flex-col sm:flex-row items-center justify-center mt-4 space-y-2 sm:space-y-0 sm:space-x-6 text-xs sm:text-sm text-gray-400">
                    <span class="flex items-center">
                        <i class="fas fa-shield-alt mr-1 sm:mr-2"></i>
                        Secure & Private
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-clock mr-1 sm:mr-2"></i>
                        Real-time Updates
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-users mr-1 sm:mr-2"></i>
                        Easy Communication
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="fab-script.js"></script>
    <script>
        // Global functions for modal handling
        function closeNoTeachersModal() {
            const modal = document.getElementById('noTeachersModal');
            if (modal) {
                const modalContent = modal.querySelector('.bg-white');
                if (modalContent) {
                    modalContent.classList.remove('scale-100', 'opacity-100');
                    modalContent.classList.add('scale-95', 'opacity-0');
                }
                
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        function refreshTeachersList() {
            // Close the modal first
            closeNoTeachersModal();
            
            // Reload the page to refresh the teachers list
            setTimeout(() => {
                window.location.reload();
            }, 350);
        }
        
        // Global variables for real-time updates
        let teacherUpdateInterval;
        let lastTeacherUpdate = '';
        let currentTeachers = [];
        
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            
            try {
            
            // QR Scanner and Student ID functionality
            const studentIdInput = document.getElementById('studentIdInput');
            const scanQRBtn = document.getElementById('scanQRBtn');
            const clearStudentIdBtn = document.getElementById('clearStudentIdBtn');
            const studentInfoDisplay = document.getElementById('studentInfoDisplay');
            const studentNameDisplay = document.getElementById('studentNameDisplay');
            const studentIdDisplay = document.getElementById('studentIdDisplay');
            const studentDeptDisplay = document.getElementById('studentDeptDisplay');
            
            let currentStudentId = '';
            let currentStudentName = '';
            let currentStudentDept = '';
            
            // Initialize QR Scanner
            function initializeQRScanner() {
                // Check if browser supports camera access
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    console.log('QR Scanner initialized - camera access available');
                } else {
                    console.log('QR Scanner initialized - camera access not available, manual input only');
                }
            }
            
            // Handle QR code input (from QR scan or manual entry)
            function handleStudentIdInput(qrCode) {
                if (!qrCode || qrCode.trim() === '') {
                    hideStudentInfo();
                    return;
                }
                
                // Clean the QR code - preserve original format
                qrCode = qrCode.trim();
                
                // Debug logging to track QR code processing
                console.log('Original QR code input:', qrCode);
                console.log('QR code type:', typeof qrCode);
                console.log('QR code length:', qrCode.length);
                
                // Process QR code through the differentiation API
                processQRCode(qrCode);
            }
            
            // Process QR code to determine if it's teacher or student
            function processQRCode(qrCode) {
                console.log('Processing QR code:', qrCode);
                
                // Log QR processing (no visual notification)
                console.log('Processing QR code...');
                
                // Send QR code to processing API
                const formData = new FormData();
                formData.append('qr_code', qrCode);
                
                fetch('../api/process-qr-scan.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('QR processing response:', data);
                    
                    if (data.success) {
                        if (data.type === 'teacher') {
                            // Handle teacher QR code
                            handleTeacherQRCode(data);
                        } else if (data.type === 'student') {
                            // Handle student QR code
                            handleStudentQRCode(data);
                        }
                    } else {
                        console.error('QR processing failed:', data.error);
                        // Clear the input field
                        if (studentIdInput) {
                            studentIdInput.value = '';
                        }
                        hideStudentInfo();
                    }
                })
                .catch(error => {
                    console.error('QR processing error:', error);
                    // Clear the input field
                    if (studentIdInput) {
                        studentIdInput.value = '';
                    }
                    hideStudentInfo();
                });
            }
            
            // Handle teacher QR code scan
            function handleTeacherQRCode(data) {
                console.log('Teacher QR code detected:', data.teacher.name);
                
                if (data.requires_confirmation) {
                    // Show teacher confirmation modal
                    showTeacherConfirmationModal(data);
                } else {
                    // Show teacher availability modal (for backward compatibility)
                    showTeacherAvailabilityModal(data);
                }
                
                // Clear the input field for next scan
                if (studentIdInput) {
                    studentIdInput.value = '';
                }
            }
            
            // Handle student QR code scan
            function handleStudentQRCode(data) {
                console.log('Student QR code detected:', data.student.id);
                
                const studentId = data.student.id;
                
                // Store the student ID exactly as received
                currentStudentId = studentId;
                currentStudentName = data.student.name;
                currentStudentDept = data.student.department;
                
                // Display student info
                displayStudentInfo({
                    name: data.student.name,
                    department: data.student.department,
                    id: studentId
                });
                
                // Store in session storage for use in consultation requests
                sessionStorage.setItem('currentStudentId', studentId);
                sessionStorage.setItem('currentStudentName', data.student.name);
                sessionStorage.setItem('currentStudentDept', data.student.department);
                
                console.log('Student ID processed and stored:', studentId);
                console.log('Student name:', currentStudentName);
                console.log('Session storage updated');
                
                // Log success message
                console.log('Student QR processed:', data.message);
            }
            

            
            // Display student information
            function displayStudentInfo(studentData) {
                studentNameDisplay.textContent = studentData.name;
                studentIdDisplay.textContent = studentData.id || currentStudentId;
                studentDeptDisplay.textContent = studentData.department;
                
                // Show student info - simple
                studentInfoDisplay.classList.remove('hidden');
                
                // Show teachers section
                showTeachersSection();
            }
            
                    // Hide student information
        function hideStudentInfo() {
            studentInfoDisplay.classList.add('hidden');
            currentStudentId = '';
            currentStudentName = '';
            currentStudentDept = '';
            
            // Clear session storage
            sessionStorage.removeItem('currentStudentId');
            sessionStorage.removeItem('currentStudentName');
            sessionStorage.removeItem('currentStudentDept');
            
            // Hide teachers section
            hideTeachersSection();
        }
        
        // Clear student ID field and reset everything
        function clearStudentIdField() {
            console.log('Clearing student ID field and resetting student info');
            
            // Clear the input field
            if (studentIdInput) {
                studentIdInput.value = '';
            }
            
            // Hide student info display
            hideStudentInfo();
            
            // Focus back to the input field for next scan
            setTimeout(() => {
                if (studentIdInput) {
                    studentIdInput.focus();
                    console.log('Student ID field cleared and focused for next scan');
                }
            }, 100);
        }
            
            // Event listeners for student ID input
            if (studentIdInput) {
                // Handle manual input
                studentIdInput.addEventListener('input', function(e) {
                    const value = e.target.value;
                    if (value.length >= 8) { // Minimum length for student ID
                        handleStudentIdInput(value);
                    } else {
                        hideStudentInfo();
                    }
                });
                
                // Handle Enter key
                studentIdInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        handleStudentIdInput(this.value);
                    }
                });
                
                // Prevent losing focus
                studentIdInput.addEventListener('blur', function(e) {
                    // Only allow blur if clicking on QR scan button or clear button
                    const relatedTarget = e.relatedTarget;
                    if (!relatedTarget || 
                        (!relatedTarget.id.includes('scanQR') && 
                         !relatedTarget.id.includes('clearStudentId') &&
                         !relatedTarget.closest('#qrScannerModal'))) {
                        // Refocus after a short delay
                        setTimeout(() => {
                            if (this.offsetParent !== null) { // Only if still visible
                                this.focus();
                            }
                        }, 100);
                    }
                });
                
                // Auto-focus on page load
                setTimeout(() => {
                    studentIdInput.focus();
                }, 500);
                
                // Focus on any click on the page (except on modals)
                document.addEventListener('click', function(e) {
                    // Don't refocus if clicking on modals, buttons, or other interactive elements
                    if (!e.target.closest('.modal') && 
                        !e.target.closest('button') && 
                        !e.target.closest('a') &&
                        !e.target.closest('#qrScannerModal') &&
                        !e.target.closest('#fabModal')) {
                        setTimeout(() => {
                            if (studentIdInput && studentIdInput.offsetParent !== null) {
                                studentIdInput.focus();
                            }
                        }, 100);
                    }
                });
            }
            
            // QR Scan button functionality
            if (scanQRBtn) {
                scanQRBtn.addEventListener('click', function() {
                    openQRScanner();
                });
            }
            
            // QR Scanner functionality
            let html5QrcodeScanner = null;
            
            function openQRScanner() {
                const modal = document.getElementById('qrScannerModal');
                const container = document.getElementById('qrScannerContainer');
                
                if (modal && container) {
                    modal.classList.remove('hidden');
                    
                    // Initialize QR scanner
                    if (!html5QrcodeScanner) {
                        html5QrcodeScanner = new Html5QrcodeScanner(
                            "qrScannerContainer",
                            { 
                                fps: 10, 
                                qrbox: { width: 250, height: 250 },
                                aspectRatio: 1.0
                            },
                            false
                        );
                        
                        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                    }
                }
            }
            
            function closeQRScannerModal() {
                const modal = document.getElementById('qrScannerModal');
                if (modal) {
                    modal.classList.add('hidden');
                    
                    // Stop scanner if active
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.clear();
                        html5QrcodeScanner = null;
                    }
                }
            }
            
            function onScanSuccess(decodedText, decodedResult) {
                console.log('QR Code scanned:', decodedText);
                
                // Close scanner
                closeQRScannerModal();
                
                // Set the scanned value to input
                if (studentIdInput) {
                    studentIdInput.value = decodedText;
                    handleStudentIdInput(decodedText);
                }
                
                // Log QR scan success
                console.log('QR Code scanned successfully!');
            }
            
            function onScanFailure(error) {
                // Handle scan failure silently
                console.log('QR scan failed:', error);
            }
            
            // Make functions globally accessible
            window.openQRScanner = openQRScanner;
            window.closeQRScannerModal = closeQRScannerModal;
            
            // Simple notification function (disabled - no notifications)
            function showNotification(message, type = 'info') {
                // Notifications disabled for student screen
                console.log(`Notification (${type}): ${message}`);
            }
            
            // Clear button functionality
            if (clearStudentIdBtn) {
                clearStudentIdBtn.addEventListener('click', function() {
                    studentIdInput.value = '';
                    hideStudentInfo();
                    studentIdInput.focus();
                });
            }
            
            // Initialize QR scanner
            initializeQRScanner();
            
            // Show/hide teachers section functions
            function showTeachersSection() {
                const teachersSection = document.getElementById('teachersSection');
                const notice = document.getElementById('studentIdRequiredNotice');
                
                // Show teachers section - simple
                if (teachersSection) {
                    teachersSection.classList.remove('hidden');
                    teachersSection.style.display = 'block';
                }
                
                // Hide the notice - simple
                if (notice) {
                    notice.style.display = 'none';
                }
                
                // Initialize teacher card event listeners after showing the section
                setTimeout(() => {
                    initializeTeacherCardListeners();
                    
                    // Start real-time teacher updates first
                    startTeacherUpdates();
                    
                    // Delay the availability check to allow real-time updates to load teachers first
                    setTimeout(() => {
                        checkTeachersAvailability();
                    }, 2000); // Wait 2 seconds for API call to complete
                }, 100);
                
                console.log('Teachers section shown - real-time updates started');
            }
            
            // Check if teachers are available and show modal if not
            function checkTeachersAvailability() {
                const teachersSection = document.getElementById('teachersSection');
                if (teachersSection) {
                    const teacherCards = teachersSection.querySelectorAll('.teacher-card');
                    if (teacherCards.length === 0) {
                        // No teachers available, show modal
                        showNoTeachersModal();
                    }
                }
            }
            
            // Show no teachers available modal
            function showNoTeachersModal() {
                const modal = document.getElementById('noTeachersModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.style.display = 'block';
                    
                    // Add animation
                    setTimeout(() => {
                        const modalContent = modal.querySelector('.bg-white');
                        if (modalContent) {
                            modalContent.classList.remove('scale-95', 'opacity-0');
                            modalContent.classList.add('scale-100', 'opacity-100');
                        }
                    }, 10);
                }
                
                // Clear the QR input field when no teachers are available
                const studentIdInput = document.getElementById('studentIdInput');
                if (studentIdInput) {
                    studentIdInput.value = '';
                    // Also clear session storage
                    sessionStorage.removeItem('currentStudentId');
                    sessionStorage.removeItem('currentStudentName');
                    sessionStorage.removeItem('currentStudentDept');
                    // Hide student info display
                    const studentInfoDisplay = document.getElementById('studentInfoDisplay');
                    if (studentInfoDisplay) {
                        studentInfoDisplay.classList.add('hidden');
                    }
                }
            }
            
            // Hide no teachers modal
            function hideNoTeachersModal() {
                const modal = document.getElementById('noTeachersModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                    
                    // Remove animation classes
                    const modalContent = modal.querySelector('.bg-white');
                    if (modalContent) {
                        modalContent.classList.add('scale-95', 'opacity-0');
                        modalContent.classList.remove('scale-100', 'opacity-100');
                    }
                }
            }

            
            function hideTeachersSection() {
                const teachersSection = document.getElementById('teachersSection');
                const notice = document.getElementById('studentIdRequiredNotice');
                
                // Hide teachers section
                if (teachersSection) {
                    teachersSection.classList.add('hidden');
                    teachersSection.style.display = 'none';
                }
                
                // Show the notice
                if (notice) {
                    notice.style.display = 'block';
                }
                
                // Stop real-time teacher updates
                stopTeacherUpdates();
                
                console.log('Teachers section hidden - real-time updates stopped');
            }
            
            // Initialize teachers section as hidden
            hideTeachersSection();
            
            // Check if student ID already exists in session storage
            const existingStudentId = sessionStorage.getItem('currentStudentId');
            if (existingStudentId) {
                // Restore student info from session storage
                currentStudentId = existingStudentId;
                currentStudentName = sessionStorage.getItem('currentStudentName') || ('Student ' + existingStudentId);
                currentStudentDept = sessionStorage.getItem('currentStudentDept') || 'General';
                
                // Update input field
                if (studentIdInput) {
                    studentIdInput.value = existingStudentId;
                }
                
                // Display student info
                displayStudentInfo({
                    name: currentStudentName,
                    department: currentStudentDept
                });
                
                // Initialize teacher card listeners since teachers section will be shown
                setTimeout(() => {
                    initializeTeacherCardListeners();
                }, 200);
                
                console.log('Restored student ID from session:', existingStudentId);
            }
            

            
            // Live Clock Functionality
            function updateLiveTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
                
                const liveTimeElement = document.getElementById('liveTime');
                if (liveTimeElement) {
                    // Add subtle animation when time updates
                    liveTimeElement.classList.add('time-updating');
                    
                    // Update the time
                    liveTimeElement.textContent = timeString;
                    
                    // Remove animation class after animation completes
                    setTimeout(() => {
                        liveTimeElement.classList.remove('time-updating');
                    }, 1000);
                }
            }
            
            // Update time immediately and then every second
            updateLiveTime();
            setInterval(updateLiveTime, 1000);
            
            console.log('Live clock initialized');
            
                    // Initialize FAB with selected department
        window.selectedDepartment = '<?php echo htmlspecialchars($selected_department ?? ""); ?>';
        
        // Make showConfirmationDialog globally available for FAB
        window.showConfirmationDialog = showConfirmationDialog;
        
        // Start real-time teacher updates
        function startTeacherUpdates() {
            console.log('Starting real-time teacher updates');
            
            // Show real-time indicator
            const indicator = document.getElementById('realtimeIndicator');
            if (indicator) {
                indicator.classList.remove('hidden');
                indicator.classList.add('flex');
            }
            
            // Update immediately
            updateAvailableTeachers();
            
            // Then update every 5 seconds
            teacherUpdateInterval = setInterval(() => {
                updateAvailableTeachers();
            }, 5000);
        }
        
        // Stop real-time teacher updates
        function stopTeacherUpdates() {
            if (teacherUpdateInterval) {
                clearInterval(teacherUpdateInterval);
                teacherUpdateInterval = null;
                console.log('Stopped real-time teacher updates');
            }
            
            // Hide real-time indicator
            const indicator = document.getElementById('realtimeIndicator');
            if (indicator) {
                indicator.classList.add('hidden');
                indicator.classList.remove('flex');
            }
        }
        
        // Update available teachers
        function updateAvailableTeachers() {
            // Only update if student ID is provided and teachers section is visible
            const teachersSection = document.getElementById('teachersSection');
            const studentId = sessionStorage.getItem('currentStudentId');
            
            if (!studentId || !teachersSection || teachersSection.classList.contains('hidden')) {
                return;
            }
            
            // Show loading animation on teachers grid
            const teachersGrid = teachersSection.querySelector('.grid');
            if (teachersGrid && teachersGrid.children.length > 0) {
                teachersGrid.style.opacity = '0.7';
                teachersGrid.style.transform = 'scale(0.98)';
                teachersGrid.style.transition = 'all 0.3s ease';
            }
            
            const department = window.selectedDepartment || '';
            const url = `../api/get-available-teachers.php?dept=${encodeURIComponent(department)}&last_update=${encodeURIComponent(lastTeacherUpdate)}&t=${Date.now()}`;
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Restore teachers grid to normal state
                    const teachersGrid = teachersSection.querySelector('.grid');
                    if (teachersGrid) {
                        teachersGrid.style.opacity = '1';
                        teachersGrid.style.transform = 'scale(1)';
                        teachersGrid.style.transition = 'all 0.3s ease';
                    }
                    
                    updateTeachersDisplay(data.teachers);
                    lastTeacherUpdate = data.last_update;
                    console.log(`Updated teachers list: ${data.count} teachers available`);
                } else {
                    console.error('Failed to fetch teachers:', data.error);
                    
                    // Restore teachers grid to normal state on error
                    const teachersGrid = teachersSection.querySelector('.grid');
                    if (teachersGrid) {
                        teachersGrid.style.opacity = '1';
                        teachersGrid.style.transform = 'scale(1)';
                        teachersGrid.style.transition = 'all 0.3s ease';
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching teachers:', error);
            });
        }
        
                // Update teachers display - simplified
        function updateTeachersDisplay(newTeachers) {
            const teachersGrid = document.querySelector('#teachersSection .grid');
            if (!teachersGrid) return;
            
            // Check if teachers list has changed
            const newTeacherIds = newTeachers.map(t => t.id).sort();
            const currentTeacherIds = currentTeachers.map(t => t.id).sort();
            
            if (JSON.stringify(newTeacherIds) === JSON.stringify(currentTeacherIds)) {
                // No changes in teacher list
                return;
            }
            
            // Store new teachers list
            currentTeachers = newTeachers;
            
            // If we now have teachers, hide the no teachers modal
            if (newTeachers.length > 0) {
                hideNoTeachersModal();
            }
            
            // Show notification for newly available teachers
            const newlyAvailable = newTeachers.filter(newTeacher => 
                !currentTeacherIds.includes(newTeacher.id)
            );
            
            if (newlyAvailable.length > 0 && currentTeacherIds.length > 0) {
                showNewTeacherNotification(newlyAvailable);
            }
            
            // Clear existing content
            teachersGrid.innerHTML = '';
            
            if (newTeachers.length === 0) {
                // Show no teachers message
                teachersGrid.innerHTML = `
                    <div class="col-span-full bg-white rounded-lg shadow-md p-8 text-center">
                        <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Teachers Available Now</h3>
                        <p class="text-gray-500 mb-4">
                            ${window.selectedDepartment ? 
                                `No teachers from <strong>${window.selectedDepartment}</strong> are currently available for consultation.` :
                                'No teachers are currently available for consultation.'
                            }
                        </p>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                            <p class="text-yellow-800 text-sm">
                                <i class="fas fa-info-circle mr-2"></i>
                                Teachers appear here when they scan their QR code to mark themselves as available.
                            </p>
                        </div>
                        <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span>Checking for updates every 5 seconds...</span>
                        </div>
                    </div>
                `;
                return;
            }
            
            // Add teachers to grid - simple and clean
            newTeachers.forEach(teacher => {
                const teacherCard = createTeacherCard(teacher);
                teachersGrid.appendChild(teacherCard);
            });
            
            // Initialize teacher card listeners
            setTimeout(() => {
                initializeTeacherCardListeners();
            }, 100);
        }
        
        // Create teacher card element - simplified
        function createTeacherCard(teacher) {
            const card = document.createElement('div');
            card.className = 'teacher-card bg-white rounded-xl shadow-lg hover:shadow-2xl cursor-pointer transition-all duration-300 border border-gray-200 flex flex-col overflow-hidden';
            card.setAttribute('data-teacher-id', teacher.id);
            card.setAttribute('data-teacher-name', `${teacher.first_name} ${teacher.last_name}`);
            card.setAttribute('data-teacher-dept', teacher.department);
            
            // Generate initials if no image
            const firstInitial = teacher.first_name.charAt(0).toUpperCase();
            const lastInitial = teacher.last_name.charAt(0).toUpperCase();
            const initials = firstInitial + lastInitial;
            
            card.innerHTML = `
                <!-- Teacher Avatar at Top -->
                <div class="p-6 flex justify-center bg-gradient-to-br from-gray-50 to-white border-b border-gray-100">
                    <div class="teacher-avatar">
                        ${teacher.image_url ? 
                            `<img src="../${teacher.image_url}" alt="Teacher" class="w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover border-4 border-orange-500 shadow-lg">` :
                            `<div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center border-4 border-orange-500 shadow-lg">
                                <span class="text-white text-xl sm:text-2xl font-bold">${initials}</span>
                            </div>`
                        }
                    </div>
                </div>

                <!-- Teacher Information - Centered -->
                <div class="p-4 sm:p-6 flex-1 flex flex-col justify-center">
                    <div class="text-center space-y-3">
                        <!-- Teacher Name -->
                        <h3 class="text-lg sm:text-xl font-bold text-gray-800">
                            ${teacher.first_name} ${teacher.last_name}
                        </h3>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="px-4 sm:px-6 py-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-b-xl">
                    <div class="flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-xs sm:text-sm text-orange-700 font-medium mb-2">Tap to start consultation</div>
                            <div class="flex items-center justify-center space-x-1 sm:space-x-2">
                                <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse"></div>
                                <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                                <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            return card;
        }
        
        // Format time helper function
        function formatTime(timeString) {
            if (!timeString) return '';
            
            const time = new Date(`1970-01-01T${timeString}`);
            return time.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
        
        // Show notification for newly available teachers (disabled)
        function showNewTeacherNotification(newTeachers) {
            const teacherNames = newTeachers.map(t => `${t.first_name} ${t.last_name}`).join(', ');
            const message = newTeachers.length === 1 ? 
                `🎉 ${teacherNames} is now available for consultation!` :
                `🎉 ${newTeachers.length} new teachers are now available: ${teacherNames}`;
            
            console.log(`New teacher notification (disabled): ${message}`);
            
            // Play notification sound (keep sound, remove visual notification)
            try {
                const audio = new Audio('../consultation/notification-sound.mp3');
                audio.volume = 0.3;
                audio.play().catch(e => console.log('Notification sound failed:', e));
            } catch (e) {
                console.log('Notification sound not supported:', e);
            }
        }
            
            // Department filter functionality
            const departmentFilterBtns = document.querySelectorAll('.department-filter-btn');
            departmentFilterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const department = this.getAttribute('data-dept');
                    
                    // Update button styles
                    departmentFilterBtns.forEach(b => {
                        b.classList.remove('bg-seait-orange', 'text-white');
                        b.classList.add('bg-gray-200', 'text-gray-700');
                    });
                    this.classList.remove('bg-gray-200', 'text-gray-700');
                    this.classList.add('bg-seait-orange', 'text-white');
                    
                    // Reload page with department filter
                    window.location.href = `student-screen.php?dept=${encodeURIComponent(department)}`;
                });
            });

        // Initialize teacher card event listeners
        function initializeTeacherCardListeners() {
            const teacherCards = document.querySelectorAll('.teacher-card');
            const loadingState = document.getElementById('loadingState');
            
            console.log('Initializing teacher card listeners. Found cards:', teacherCards.length);
            console.log('Loading state element:', loadingState);

            if (teacherCards.length === 0) {
                console.warn('No teacher cards found to initialize listeners');
                return;
            }

            teacherCards.forEach((card, index) => {
                console.log(`Setting up click listener for card ${index + 1}:`, card);
                
                // Remove any existing click listeners to prevent duplicates
                card.removeEventListener('click', handleTeacherCardClick);
                
                // Add new click listener
                card.addEventListener('click', handleTeacherCardClick);
                
                // Add visual feedback for clickable state
                card.style.cursor = 'pointer';
                card.classList.add('clickable');
                
                console.log(`Click listener added to card ${index + 1}`);
            });
            
            console.log(`Successfully initialized ${teacherCards.length} teacher card listeners`);
            
            // Test click functionality
            setTimeout(() => {
                console.log('Testing teacher card click functionality...');
                const testCard = document.querySelector('.teacher-card');
                if (testCard) {
                    console.log('Test card found, click listeners should be working');
                    // Add a temporary test click to verify
                    testCard.addEventListener('click', function() {
                        console.log('Test click detected - teacher card listeners are working!');
                    }, { once: true });
                }
            }, 500);
        }
        
        // Teacher card click handler
        function handleTeacherCardClick(e) {
            console.log('Teacher card clicked!');
            
            // Prevent multiple requests - check if already waiting for response
            if (isStatusChecking) {
                console.log('Already waiting for teacher response, ignoring click');
                return;
            }
            
            const teacherId = this.getAttribute('data-teacher-id');
            const teacherName = this.getAttribute('data-teacher-name');
            const teacherDept = this.getAttribute('data-teacher-dept');
            
            console.log('Teacher data:', { teacherId, teacherName, teacherDept });
            
            // Show confirmation dialog first
            showConfirmationDialog(teacherName, teacherId, teacherDept);
        }





        // Check consultation request status every 1 second for faster response
        let statusCheckInterval;
        let isStatusChecking = false;
        let checkCount = 0;
        const MAX_CHECKS = 60; // Maximum 60 seconds of checking
        
        function startStatusChecking(sessionId) {
            // Prevent multiple status checking intervals
            if (isStatusChecking) {
                console.log('Status checking already active, stopping previous interval');
                stopStatusChecking();
            }
            
            console.log('Starting status checking for session:', sessionId);
            isStatusChecking = true;
            checkCount = 0;
            
            // Check immediately first
            checkConsultationStatus(sessionId);
            
            // Then check every 500ms for the first 10 seconds for faster response
            let fastCheckCount = 0;
            const fastCheckInterval = setInterval(() => {
                fastCheckCount++;
                checkConsultationStatus(sessionId);
                
                if (fastCheckCount >= 20 || !isStatusChecking) { // 10 seconds (20 * 500ms)
                    clearInterval(fastCheckInterval);
                }
            }, 500);
            
            // Then switch to 1 second intervals
            statusCheckInterval = setInterval(() => {
                checkCount++;
                if (checkCount >= MAX_CHECKS) {
                    console.log('Maximum check time reached, stopping status checking');
                    stopStatusChecking();
                    hidePendingRequest();
                    return;
                }
                checkConsultationStatus(sessionId);
            }, 1000); // Check every 1 second after initial fast checks
        }
        
        function stopStatusChecking() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
                console.log('Status checking stopped');
            }
            isStatusChecking = false;
        }
        
        function checkConsultationStatus(sessionId) {
            if (!sessionId) return;
            
            console.log('Checking consultation status for session:', sessionId);
            
            // Add cache busting parameter to prevent browser caching
            const timestamp = new Date().getTime();
            fetch(`check-consultation-status.php?session_id=${encodeURIComponent(sessionId)}&t=${timestamp}`, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Status check response:', data);
                    
                    if (data.success === false) {
                        console.log('No request found or error:', data.error);
                        return;
                    }
                    
                    if (data.status === 'accepted') {
                        console.log('Request sent successfully! Stopping status checking...');
                        stopStatusChecking();
                        hidePendingRequest();
                        showConsultationResponse('accepted', data.teacher_name, data);
                        // Clear the session ID to prevent further checking
                        sessionStorage.removeItem('currentSessionId');
                        // Play success sound
                        playSuccessSound();
                    } else if (data.status === 'declined') {
                        console.log('Consultation declined! Stopping status checking...');
                        stopStatusChecking();
                        hidePendingRequest();
                        showConsultationResponse('declined', data.teacher_name, data);
                        // Clear the session ID to prevent further checking
                        sessionStorage.removeItem('currentSessionId');
                        // Play notification sound
                        playNotificationSound();
                    } else if (data.status === 'pending') {
                        console.log('Request still being processed, continuing to check...');
                        // Update the wait time display if function exists
                        if (typeof updateWaitTimeDisplay === 'function') {
                            updateWaitTimeDisplay();
                        }
                    } else {
                        console.log('Unknown status:', data.status);
                    }
                })
                .catch(error => {
                    console.error('Error checking consultation status:', error);
                    // Don't stop checking on network errors, just log them
                });
        }
        
        // Show confirmation dialog before sending consultation request
        function showConfirmationDialog(teacherName, teacherId, teacherDept) {
            console.log('Showing confirmation dialog for:', teacherName);
            
            // Create ultra simple confirmation modal
            const modal = document.createElement('div');
            modal.className = 'ultra-simple-modal';
            modal.id = 'confirmationModal';
            
            const modalContent = `
                <div class="ultra-simple-content" id="confirmationModalContent">
                    <!-- Enhanced Modal Header -->
                    <div class="flex items-center justify-between p-4 sm:p-6 border-b border-orange-200 bg-gradient-to-r from-orange-50 to-orange-100 rounded-t-2xl">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full flex items-center justify-center shadow-lg">
                                <i class="fas fa-question-circle text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-xl font-bold text-gray-800">Confirm Consultation Request</h3>
                                <p class="text-xs sm:text-sm text-orange-600 font-medium">Ready to Send</p>
                            </div>
                        </div>
                        <button onclick="closeConfirmationModal()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 rounded-full hover:bg-gray-100">
                            <i class="fas fa-times text-lg sm:text-xl"></i>
                        </button>
                    </div>

                    <!-- Enhanced Modal Body -->
                    <div class="p-4 sm:p-6">
                        <div class="text-center">
                            <div class="mb-6">
                                <div class="w-20 h-20 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg animate-pulse">
                                    <i class="fas fa-user-tie text-white text-3xl"></i>
                                </div>
                                <h4 class="text-xl sm:text-2xl font-bold text-gray-800 mb-3">Request Consultation?</h4>
                                <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                                    Are you sure you want to request a consultation with <strong>${teacherName}</strong>?
                                </p>
                                <div class="bg-gradient-to-r from-orange-50 to-orange-100 border border-orange-200 rounded-xl p-4 mb-4">
                                    <div class="flex items-center justify-center space-x-2 mb-2">
                                        <i class="fas fa-info-circle text-orange-600"></i>
                                        <span class="text-sm font-semibold text-orange-800">What Happens Next?</span>
                                    </div>
                                    <p class="text-xs sm:text-sm text-orange-700">
                                        This will send a notification to the teacher. You'll be notified when they respond with an acceptance or decline.
                                    </p>
                                </div>
                                <div class="flex items-center justify-center space-x-4 text-xs sm:text-sm text-gray-500">
                                    <span class="flex items-center">
                                        <i class="fas fa-bell mr-1"></i>
                                        Notification
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-clock mr-1"></i>
                                        Real-time
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        Secure
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Modal Footer -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 p-4 sm:p-6 border-t border-orange-200 bg-gradient-to-r from-orange-50 to-orange-100 rounded-b-2xl">
                        <button onclick="closeConfirmationModal()"
                                class="flex-1 bg-gradient-to-r from-gray-500 to-gray-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-gray-600 hover:to-gray-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button onclick="confirmConsultationRequest('${teacherId}', '${teacherName}', '${teacherDept}')"
                                class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-orange-600 hover:to-orange-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                            <i class="fas fa-paper-plane mr-2"></i>Send Request
                        </button>
                    </div>
                </div>
            `;
            
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
            
            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeConfirmationModal();
                }
            });

            // Close modal with Escape key
            const handleEscape = function(event) {
                if (event.key === 'Escape') {
                    closeConfirmationModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        }
        
        // Ultra simple confirmation modal close
        function closeConfirmationModal() {
            const modal = document.getElementById('confirmationModal');

            if (modal) {
                modal.remove();
                document.body.style.overflow = '';
                cleanupBlurEffects(); // Aggressive cleanup
            }
        }
        
        // Confirm and send consultation request
        function confirmConsultationRequest(teacherId, teacherName, teacherDept) {
            console.log('Confirming consultation request for:', teacherName);
            
            // Check if student ID is provided
            const studentId = sessionStorage.getItem('currentStudentId');
            const studentName = sessionStorage.getItem('currentStudentName');
            const studentDept = sessionStorage.getItem('currentStudentDept');
            
            if (!studentId) {
                alert('Please scan your student ID first before requesting consultation.');
                // Focus back to student ID input
                const studentIdInput = document.getElementById('studentIdInput');
                if (studentIdInput) {
                    studentIdInput.focus();
                }
                return;
            }
            
            // Close confirmation modal
            closeConfirmationModal();
                
                // Show loading state
            const loadingState = document.getElementById('loadingState');
                if (loadingState) {
                    loadingState.classList.add('show');
                    console.log('Loading state shown');
                }
                
                // Store teacher info in session storage
                sessionStorage.setItem('selectedTeacherId', teacherId);
                sessionStorage.setItem('selectedTeacherName', teacherName);
                sessionStorage.setItem('selectedTeacherDept', teacherDept);
                
                // Submit consultation request via AJAX
                const formData = new FormData();
                formData.append('teacher_id', teacherId);
                formData.append('student_name', studentName || 'Student');
                formData.append('student_id', studentId);
                formData.append('student_dept', studentDept || 'General');
                
                // Debug logging for consultation request
                console.log('=== CONSULTATION REQUEST SUBMISSION DEBUG ===');
                console.log('Submitting consultation request with data:');
                console.log('- teacher_id:', teacherId);
                console.log('- student_name:', studentName || 'Student');
                console.log('- student_id:', studentId);
                console.log('- student_id type:', typeof studentId);
                console.log('- student_id length:', studentId ? studentId.length : 'null');
                console.log('- student_id raw:', JSON.stringify(studentId));
                console.log('- student_dept:', studentDept || 'General');
                console.log('Session storage contents:');
                console.log('- currentStudentId:', sessionStorage.getItem('currentStudentId'));
                console.log('- currentStudentName:', sessionStorage.getItem('currentStudentName'));
                console.log('- currentStudentDept:', sessionStorage.getItem('currentStudentDept'));
                
                fetch('submit-consultation-request.php', {
                    method: 'POST',
                    body: formData
                })
            .then(response => response.json())
                .then(data => {
                    // Hide loading
                    if (loadingState) {
                        loadingState.classList.remove('show');
                    }
                    
                    if (data.success) {
                    console.log('Consultation request successful');
                        
                        // Clear any existing session ID first
                        sessionStorage.removeItem('currentSessionId');
                        
                        // Store new session ID for reference
                        sessionStorage.setItem('currentSessionId', data.session_id);
                        
                                // Start checking for teacher response
        startStatusChecking(data.session_id);
        
        // Show pending request indicator
        showPendingRequest(teacherName);
        
        // Clear the student ID input field and reset student info
        clearStudentIdField();
        
        // Start automatic status checking for when teacher responds
        startAutomaticStatusCheck(data.session_id, teacherName);
        
        // Store teacher name in session storage for status checking
        sessionStorage.setItem('currentTeacherName', teacherName);
        
        console.log('Consultation request sent successfully');
                    } else {
                    console.log(`Error: ${data.error || 'Failed to notify teacher'}`);
                    }
                })
                .catch(error => {
                    console.error('Error in fetch:', error);
                    // Hide loading
                    if (loadingState) {
                        loadingState.classList.remove('show');
                }
                console.log('Network error. Please try again.');
            });
        }
        
        // Make functions globally accessible
        window.closeConfirmationModal = closeConfirmationModal;
        window.confirmConsultationRequest = confirmConsultationRequest;
        
        // Global variables for automatic status checking
        let automaticStatusCheckInterval = null;
        let isAutomaticStatusChecking = false;
        
        // Function to start automatic status checking after consultation request is sent
        function startAutomaticStatusCheck(sessionId, teacherName) {
            if (!sessionId || isAutomaticStatusChecking) {
                return;
            }
            
            console.log('🚀 Starting automatic status check for session:', sessionId);
            isAutomaticStatusChecking = true;
            
            // Check status every 2 seconds until we get a response
            automaticStatusCheckInterval = setInterval(() => {
                checkAutomaticStatus(sessionId, teacherName);
            }, 2000);
            
            // Set a maximum timeout (5 minutes) to prevent infinite checking
            setTimeout(() => {
                if (isAutomaticStatusChecking) {
                    console.log('⏰ Automatic status check timeout reached, stopping...');
                    stopAutomaticStatusCheck();
                    showEnhancedNotification('⏰ Status check timeout. Please refresh the page to check manually.', 'warning');
                }
            }, 300000); // 5 minutes
        }
        
        // Function to check status automatically
        function checkAutomaticStatus(sessionId, teacherName) {
            if (!sessionId) {
                stopAutomaticStatusCheck();
                return;
            }
            
            console.log('🔍 Checking automatic status for session:', sessionId);
            
            // Add cache busting parameter to prevent browser caching
            const timestamp = new Date().getTime();
            fetch(`check-consultation-status.php?session_id=${encodeURIComponent(sessionId)}&t=${timestamp}`, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('🔍 Automatic status check response:', data);
                
                if (data.success === false) {
                    console.log('❌ No request found or error:', data.error);
                    return;
                }
                
                if (data.status === 'accepted') {
                    console.log('✅ Request accepted! Stopping automatic status check...');
                    stopAutomaticStatusCheck();
                    hidePendingRequest();
                    showConsultationResponse('accepted', teacherName, data);
                    // Clear the session ID to prevent further checking
                    sessionStorage.removeItem('currentSessionId');
                    // Play success sound
                    playSuccessSound();
                    // Show success notification
                    showEnhancedNotification('🎉 Your consultation request has been accepted!', 'success');
                } else if (data.status === 'declined') {
                    console.log('❌ Consultation declined! Stopping automatic status check...');
                    stopAutomaticStatusCheck();
                    hidePendingRequest();
                    showConsultationResponse('declined', teacherName, data);
                    // Clear the session ID to prevent further checking
                    sessionStorage.removeItem('currentSessionId');
                    // Play notification sound
                    playNotificationSound();
                    // Show decline notification
                    showEnhancedNotification('❌ Your consultation request was declined.', 'warning');
                } else if (data.status === 'pending') {
                    console.log('⏳ Request still pending, continuing to check...');
                    // Update the wait time display if function exists
                    if (typeof updateWaitTimeDisplay === 'function') {
                        updateWaitTimeDisplay();
                    }
                } else {
                    console.log('❓ Unknown status:', data.status);
                }
            })
            .catch(error => {
                console.error('❌ Error in automatic status check:', error);
                // Don't stop checking on network errors, just log them
            });
        }
        
        // Function to stop automatic status checking
        function stopAutomaticStatusCheck() {
            if (automaticStatusCheckInterval) {
                clearInterval(automaticStatusCheckInterval);
                automaticStatusCheckInterval = null;
                console.log('🛑 Automatic status checking stopped');
            }
            isAutomaticStatusChecking = false;
        }
        
        // Enhanced notification function for student screen
        function showEnhancedNotification(message, type = 'info') {
            console.log(`[${type.toUpperCase()}] ${message}`);
            
            // Create notification element
            const notification = document.createElement('div');
            let bgColor = 'blue';
            let icon = 'info-circle';
            
            // Set colors based on type
            switch (type) {
                case 'success':
                    bgColor = 'green';
                    icon = 'check-circle';
                    break;
                case 'warning':
                    bgColor = 'yellow';
                    icon = 'exclamation-triangle';
                    break;
                case 'error':
                    bgColor = 'red';
                    icon = 'times-circle';
                    break;
                default:
                    bgColor = 'blue';
                    icon = 'info-circle';
            }
            
            notification.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-${bgColor}-600 text-white px-6 py-4 rounded-xl shadow-2xl z-50 transform transition-all duration-500 animate-bounce`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${icon} mr-3 text-xl"></i>
                    <span class="font-semibold">${message}</span>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Remove bounce animation after 1 second
            setTimeout(() => {
                notification.classList.remove('animate-bounce');
            }, 1000);
            
            // Remove notification after 5 seconds
            setTimeout(() => {
                notification.classList.add('opacity-0', 'transform', 'scale-95');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
        
        // Enhanced consultation response notification with audio - following teacher screen pattern
        function showConsultationResponse(response, teacherName, data = null) {
            // Log to console for debugging
            if (response === 'accepted') {
                console.log(`📤 Request Sent! Your consultation request has been sent to ${teacherName}.`);
            } else {
                console.log(`❌ Consultation Declined. ${teacherName} has declined your consultation request.`);
            }
            
            // Play audio feedback
            playResponseAudio(response);
            
            // Create ultra simple modal
            const modal = document.createElement('div');
            modal.className = 'ultra-simple-modal';
            modal.id = 'consultationResponseModal';
            
            let modalContent = '';
            
            if (response === 'accepted') {
                modalContent = `
                    <div class="ultra-simple-content" id="consultationModalContent">
                        <!-- Enhanced Modal Header -->
                        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 rounded-t-2xl">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                                    <i class="fas fa-paper-plane text-white text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg sm:text-xl font-bold text-gray-800">Request Sent Successfully</h3>
                                    <p class="text-xs sm:text-sm text-blue-600 font-medium">Waiting for Response</p>
                                </div>
                            </div>
                            <button onclick="closeConsultationModal()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 rounded-full hover:bg-gray-100">
                                <i class="fas fa-times text-lg sm:text-xl"></i>
                            </button>
                        </div>

                        <!-- Enhanced Modal Body -->
                        <div class="p-4 sm:p-6">
                            <div class="text-center">
                                <div class="mb-6">
                                    <div class="w-20 h-20 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg animate-pulse">
                                        <i class="fas fa-paper-plane text-white text-3xl"></i>
                                    </div>
                                    <h4 class="text-xl sm:text-2xl font-bold text-gray-800 mb-3">📤 Request Sent Successfully!</h4>
                                    <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                                        Your consultation request has been sent to <strong>${teacherName}</strong>.
                                    </p>
                                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-4 mb-4">
                                        <div class="flex items-center justify-center space-x-2 mb-2">
                                            <i class="fas fa-info-circle text-blue-600"></i>
                                            <span class="text-sm font-semibold text-blue-800">What Happens Next?</span>
                                        </div>
                                        <p class="text-xs sm:text-sm text-blue-700">
                                            Please wait for the teacher to reach out to you. They will contact you when they are ready to start the consultation session.
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center space-x-4 text-xs sm:text-sm text-gray-500">
                                        <span class="flex items-center">
                                            <i class="fas fa-clock mr-1"></i>
                                            Waiting
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-shield-alt mr-1"></i>
                                            Secure
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-users mr-1"></i>
                                            Connected
                                        </span>
                                    </div>
                                    <div class="mt-4 text-center">
                                        <div class="text-xs text-gray-500 mb-2">Modal will close and teachers will become clickable in <span id="modalCountdownTimer" class="font-bold text-blue-600">10</span> seconds</div>
                                        <div class="w-full bg-gray-200 rounded-full h-1">
                                            <div id="modalCountdownProgress" class="bg-blue-500 h-1 rounded-full transition-all duration-1000 ease-linear" style="width: 100%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Modal Footer -->
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 p-4 sm:p-6 border-t border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 rounded-b-2xl">
                            <button onclick="stopResponseAudio()"
                                    class="flex-1 bg-gradient-to-r from-red-500 to-red-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-red-600 hover:to-red-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                                <i class="fas fa-volume-mute mr-2"></i>Stop Audio
                            </button>
                            <button data-action="close-modal"
                                    class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-blue-600 hover:to-blue-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                                <i class="fas fa-times mr-2"></i>Close
                            </button>
                        </div>
                    </div>
                `;
            } else {
                modalContent = `
                    <div class="ultra-simple-content" id="consultationModalContent">
                        <!-- Enhanced Modal Header -->
                        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-red-200 bg-gradient-to-r from-red-50 to-red-100 rounded-t-2xl">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center shadow-lg">
                                    <i class="fas fa-times-circle text-white text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg sm:text-xl font-bold text-gray-800">Consultation Declined</h3>
                                    <p class="text-xs sm:text-sm text-red-600 font-medium">Not Available</p>
                                </div>
                            </div>
                            <button onclick="closeConsultationModal()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 rounded-full hover:bg-gray-100">
                                <i class="fas fa-times text-lg sm:text-xl"></i>
                            </button>
                        </div>

                        <!-- Enhanced Modal Body -->
                        <div class="p-4 sm:p-6">
                            <div class="text-center">
                                <div class="mb-6">
                                    <div class="w-20 h-20 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg animate-pulse">
                                        <i class="fas fa-times-circle text-white text-3xl"></i>
                                    </div>
                                    <h4 class="text-xl sm:text-2xl font-bold text-gray-800 mb-3">❌ Consultation Declined</h4>
                                    <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                                        <strong>${teacherName}</strong> has declined your consultation request.
                                    </p>
                                    ${data.decline_reason ? `
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-info-circle text-red-600 mr-2"></i>
                                            <span class="text-sm font-semibold text-red-800">Reason:</span>
                                        </div>
                                        <p class="text-sm text-red-700">${data.decline_reason}</p>
                                    </div>
                                    ` : ''}
                                    <div class="bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-xl p-4 mb-4">
                                        <div class="flex items-center justify-center space-x-2 mb-2">
                                            <i class="fas fa-info-circle text-red-600"></i>
                                            <span class="text-sm font-semibold text-red-800">What's Next?</span>
                                        </div>
                                        <p class="text-xs sm:text-sm text-red-700">
                                            Don't worry! You can try requesting another teacher or try again later. There are many other teachers available.
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center space-x-4 text-xs sm:text-sm text-gray-500">
                                        <span class="flex items-center">
                                            <i class="fas fa-search mr-1"></i>
                                            Find Others
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-clock mr-1"></i>
                                            Try Later
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-users mr-1"></i>
                                            Many Available
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Modal Footer -->
                        <div class="flex flex-col space-y-2 p-4 sm:p-6 border-t border-red-200 bg-gradient-to-r from-red-50 to-red-100 rounded-b-2xl">
                            <button onclick="stopResponseAudio()"
                                    class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-red-600 hover:to-red-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                                <i class="fas fa-volume-mute mr-2"></i>Stop Audio
                            </button>
                            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                                <button onclick="tryAnotherTeacher()"
                                        class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-blue-600 hover:to-blue-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                                    <i class="fas fa-search mr-2"></i>Find Another Teacher
                                </button>
                                <button data-action="close-modal"
                                        class="flex-1 bg-gradient-to-r from-gray-500 to-gray-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-gray-600 hover:to-gray-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                                    <i class="fas fa-times mr-2"></i>Close
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
            
            console.log('Modal created and appended to body');
            
            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
            
            // Add event listener for close buttons using event delegation
            modal.addEventListener('click', function(e) {
                if (e.target.closest('[data-action="close-modal"]')) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Close button clicked via event delegation');
                    closeConsultationModal();
                }
            });
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeConsultationModal();
                }
            });

            // Close modal with Escape key
            const handleEscape = function(event) {
                if (event.key === 'Escape') {
                    closeConsultationModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
            
            // Start countdown timer for modal
            let modalSecondsLeft = 10;
            const modalCountdownTimerElement = document.getElementById('modalCountdownTimer');
            const modalCountdownProgressElement = document.getElementById('modalCountdownProgress');
            
            const modalCountdownInterval = setInterval(() => {
                modalSecondsLeft--;
                
                // Update countdown display
                if (modalCountdownTimerElement) {
                    modalCountdownTimerElement.textContent = modalSecondsLeft;
                }
                
                // Update progress bar
                if (modalCountdownProgressElement) {
                    const progressPercentage = (modalSecondsLeft / 10) * 100;
                    modalCountdownProgressElement.style.width = progressPercentage + '%';
                    
                    // Change color as time runs out
                    if (modalSecondsLeft <= 3) {
                        modalCountdownProgressElement.classList.remove('bg-blue-500');
                        modalCountdownProgressElement.classList.add('bg-red-500');
                    } else if (modalSecondsLeft <= 5) {
                        modalCountdownProgressElement.classList.remove('bg-blue-500');
                        modalCountdownProgressElement.classList.add('bg-yellow-500');
                    }
                }
                
                // Auto-close when countdown reaches 0
                if (modalSecondsLeft <= 0) {
                    clearInterval(modalCountdownInterval);
                    closeConsultationModal();
                    // Re-enable teacher cards after modal closes
                    setTimeout(() => {
                        console.log('✅ Re-enabling teacher cards after consultation request...');
                        reEnableTeacherCards();
                    }, 500); // Small delay to ensure modal is fully closed
                }
            }, 1000);
        }
        
        // Global audio variables for continuous playback
        let responseAudio = null;
        let audioInterval = null;
        
        // Enhanced audio feedback for consultation response with continuous playback
        function playResponseAudio(response) {
            console.log(`Playing audio for ${response} response`);
            
            // Check if user has interacted with the page (required for autoplay)
            if (!window.userInteracted) {
                console.log('User has not interacted with page yet, enabling audio...');
                window.userInteracted = true;
            }
            
            // Stop any existing audio
            stopResponseAudio();
            
            try {
                if (response === 'accepted') {
                    // Success notification sound - bell ring
                    responseAudio = new Audio('notification-sound.mp3');
                } else {
                    // Decline notification sound - buzzer
                    responseAudio = new Audio('notification-declined.mp3');
                }
                
                responseAudio.volume = 0.5; // Increased volume slightly
                
                // Add event listeners for better debugging
                responseAudio.addEventListener('loadstart', () => console.log('Audio loading started'));
                responseAudio.addEventListener('canplay', () => console.log('Audio can play'));
                responseAudio.addEventListener('canplaythrough', () => console.log('Audio can play through'));
                responseAudio.addEventListener('error', (e) => console.log('Audio error:', e));
                responseAudio.addEventListener('ended', () => console.log('Audio playback ended'));
                
                // Play audio immediately
                const playPromise = responseAudio.play();
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log('Audio played successfully');
                        
                        // Set up continuous playback every 2 seconds with enhanced notification sound
                        audioInterval = setInterval(() => {
                            if (responseAudio) {
                                responseAudio.currentTime = 0; // Reset to beginning
                                responseAudio.play().catch(e => {
                                    console.log('Continuous audio playback failed:', e);
                                    playFallbackSound(response);
                                });
                            } else {
                                // Use fallback continuous notification sound
                                playFallbackSound(response);
                            }
                        }, 2000); // Repeat every 2 seconds for more frequent notifications
                        
                        // Set maximum duration (2 minutes) to prevent infinite sound
                        setTimeout(() => {
                            if (audioInterval) {
                                console.log('Maximum audio duration reached, stopping automatically');
                                stopResponseAudio();
                            }
                        }, 120000); // 2 minutes
                        
                    }).catch(e => {
                        console.log('Audio playback failed:', e);
                        // Fallback to browser notification sound
                        playFallbackSound(response);
                    });
                }
            } catch (e) {
                console.log('Audio not supported:', e);
                // Fallback to browser notification sound
                playFallbackSound(response);
            }
        }
        
        // Stop response audio
        function stopResponseAudio() {
            console.log('stopResponseAudio called');
            if (responseAudio) {
                responseAudio.pause();
                responseAudio.currentTime = 0;
                responseAudio = null;
            }
            
            if (audioInterval) {
                clearInterval(audioInterval);
                audioInterval = null;
            }
            
            console.log('Response audio stopped');
        }
        
        // Make functions globally accessible
        window.stopResponseAudio = stopResponseAudio;
        
        // Play success sound for accepted requests
        function playSuccessSound() {
            try {
                const successAudio = new Audio('notification-sound.mp3');
                successAudio.volume = 0.4;
                successAudio.play().catch(e => {
                    console.log('Success audio failed:', e);
                    playWebAudioFallback('accepted');
                });
            } catch (e) {
                console.log('Success audio not supported:', e);
                playWebAudioFallback('accepted');
            }
        }
        
        // Play notification sound for declined requests
        function playNotificationSound() {
            try {
                const notificationAudio = new Audio('notification-declined.mp3');
                notificationAudio.volume = 0.4;
                notificationAudio.play().catch(e => {
                    console.log('Notification audio failed:', e);
                    playWebAudioFallback('declined');
                });
            } catch (e) {
                console.log('Notification audio not supported:', e);
                playWebAudioFallback('declined');
            }
        }
        
        // Enhanced fallback sound using downloaded audio files
        function playFallbackSound(response = 'accepted') {
            try {
                // Use the downloaded audio files as fallback
                let fallbackAudio;
                if (response === 'accepted') {
                    fallbackAudio = new Audio('notification-sound.mp3');
                } else {
                    fallbackAudio = new Audio('notification-declined.mp3');
                }
                
                fallbackAudio.volume = 0.4;
                fallbackAudio.play().catch(e => {
                    console.log('Fallback audio file failed:', e);
                    // If even the fallback fails, use Web Audio API as last resort
                    playWebAudioFallback(response);
                });
                
                console.log(`Fallback audio played: ${response}`);
            } catch (e) {
                console.log('Fallback audio not supported:', e);
                // Use Web Audio API as last resort
                playWebAudioFallback(response);
            }
        }
        
        // Web Audio API fallback as last resort
        function playWebAudioFallback(response = 'accepted') {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                // Different frequencies for different responses
                const frequency = response === 'accepted' ? 800 : 400;
                const duration = response === 'accepted' ? 0.3 : 0.5;
                
                oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime);
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + duration);
                
                console.log(`Web Audio fallback: ${response} (${frequency}Hz, ${duration}s)`);
            } catch (e) {
                console.log('Web Audio fallback not supported:', e);
            }
        }
        
        // Ultra simple modal close
        function closeConsultationModal() {
            console.log('closeConsultationModal called');
            
            // Prevent multiple calls
            if (window.isClosingModal) {
                console.log('Modal already being closed, ignoring call');
                return;
            }
            
            window.isClosingModal = true;
            
            const modal = document.getElementById('consultationResponseModal');

            if (modal) {
                console.log('Closing modal...');
                // Stop the audio first
                stopResponseAudio();
                
                // Stop status checking and clear session ID
                stopStatusChecking();
                sessionStorage.removeItem('currentSessionId');
                
                // Remove modal immediately
                modal.remove();
                document.body.style.overflow = ''; // Restore scrolling
                cleanupBlurEffects(); // Aggressive cleanup
                console.log('Modal removed successfully');
            } else {
                console.log('Modal element not found');
            }
            
            // Clear student ID field after consultation response
            setTimeout(() => {
                            clearStudentIdField();
        }, 200);
        
        // Re-enable teacher cards when modal is closed
        setTimeout(() => {
            reEnableTeacherCards();
        }, 300);
        
        // Reset flag after a short delay
            setTimeout(() => {
                window.isClosingModal = false;
            }, 100);
        }
        
        // Make function globally accessible
        window.closeConsultationModal = closeConsultationModal;
        

        
        // Try another teacher
        function tryAnotherTeacher() {
            console.log('tryAnotherTeacher called');
            // Stop audio first
            stopResponseAudio();
            // Stop status checking and clear session ID
            stopStatusChecking();
            sessionStorage.removeItem('currentSessionId');
            // Close modal
            closeConsultationModal();
            // Remove any remaining blur effects
            cleanupBlurEffects();
            // Clear student ID field for fresh start
            clearStudentIdField();
            // Re-enable teacher cards instead of reloading page
            setTimeout(() => {
                reEnableTeacherCards();
            }, 500);
        }
        
        // Make function globally accessible
        window.tryAnotherTeacher = tryAnotherTeacher;
        
        // Show teacher availability modal when teacher QR is scanned
        function showTeacherAvailabilityModal(data) {
            console.log('Showing teacher availability modal for:', data.teacher.name);
            
            // Create teacher availability modal
            const modal = document.createElement('div');
            modal.className = 'ultra-simple-modal';
            modal.id = 'teacherAvailabilityModal';
            
            let modalContent = `
                <div class="ultra-simple-content" id="teacherAvailabilityModalContent">
                    <!-- Enhanced Modal Header -->
                    <div class="flex items-center justify-between p-4 sm:p-6 border-b border-green-200 bg-gradient-to-r from-green-50 to-green-100 rounded-t-2xl">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                                <i class="fas fa-user-check text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-xl font-bold text-gray-800">Teacher Available</h3>
                                <p class="text-xs sm:text-sm text-green-600 font-medium">QR Code Processed</p>
                            </div>
                        </div>
                        <button onclick="closeTeacherAvailabilityModal()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 rounded-full hover:bg-gray-100">
                            <i class="fas fa-times text-lg sm:text-xl"></i>
                        </button>
                    </div>

                    <!-- Enhanced Modal Body -->
                    <div class="p-4 sm:p-6">
                        <div class="text-center">
                            <div class="mb-6">
                                <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg animate-pulse">
                                    <i class="fas fa-user-check text-white text-3xl"></i>
                                </div>
                                <h4 class="text-xl sm:text-2xl font-bold text-gray-800 mb-3">✅ Teacher Now Available</h4>
                                <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                                    <strong>${data.teacher.name}</strong> from <strong>${data.teacher.department}</strong> is now available for consultation.
                                </p>
                                
                                ${data.auto_accepted ? `
                                <div class="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-4 mb-4">
                                    <div class="flex items-center justify-center space-x-2 mb-2">
                                        <i class="fas fa-check-circle text-blue-600"></i>
                                        <span class="text-sm font-semibold text-blue-800">Consultation Request Auto-Accepted!</span>
                                    </div>
                                    <p class="text-xs sm:text-sm text-blue-700">
                                        A pending consultation request for <strong>${data.pending_request.student_name}</strong> (${data.pending_request.student_id}) has been automatically accepted.
                                    </p>
                                </div>
                                ` : ''}
                                
                                <div class="bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-xl p-4 mb-4">
                                    <div class="flex items-center justify-center space-x-2 mb-2">
                                        <i class="fas fa-info-circle text-green-600"></i>
                                        <span class="text-sm font-semibold text-green-800">System Update</span>
                                    </div>
                                    <p class="text-xs sm:text-sm text-green-700">
                                        Teacher status has been updated to "Available for Consultation" and will appear in the student consultation system.
                                    </p>
                                </div>
                                
                                <div class="flex items-center justify-center space-x-4 text-xs sm:text-sm text-gray-500">
                                    <span class="flex items-center">
                                        <i class="fas fa-qrcode mr-1"></i>
                                        QR Scanned
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-clock mr-1"></i>
                                        Real-time
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-users mr-1"></i>
                                        Available
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Modal Footer -->
                    <div class="flex flex-col space-y-2 p-4 sm:p-6 border-t border-green-200 bg-gradient-to-r from-green-50 to-green-100 rounded-b-2xl">
                        <button onclick="closeTeacherAvailabilityModal()"
                                class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-green-600 hover:to-green-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                            <i class="fas fa-check mr-2"></i>Continue
                        </button>
                        
                        ${data.auto_accepted ? `
                        <button onclick="redirectToTeacherScreen()"
                                class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-blue-600 hover:to-blue-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                            <i class="fas fa-external-link-alt mr-2"></i>Go to Teacher Screen
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
            
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
            
            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeTeacherAvailabilityModal();
                }
            });

            // Close modal with Escape key
            const handleEscape = function(event) {
                if (event.key === 'Escape') {
                    closeTeacherAvailabilityModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
            
            // Auto-close after 8 seconds if no pending request was auto-accepted
            if (!data.auto_accepted) {
                setTimeout(() => {
                    closeTeacherAvailabilityModal();
                }, 8000);
            }
            
            // Play success sound
            try {
                const successAudio = new Audio('notification-sound.mp3');
                successAudio.volume = 0.3;
                successAudio.play().catch(e => console.log('Success audio failed:', e));
            } catch (e) {
                console.log('Success audio not supported:', e);
            }
        }
        
        // Close teacher availability modal
        function closeTeacherAvailabilityModal() {
            const modal = document.getElementById('teacherAvailabilityModal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = '';
                cleanupBlurEffects();
            }
            
            // Clear the input field for next scan
            const studentIdInput = document.getElementById('studentIdInput');
            if (studentIdInput) {
                setTimeout(() => {
                    studentIdInput.focus();
                }, 100);
            }
        }
        
        // Redirect to teacher screen (if auto-accepted request)
        function redirectToTeacherScreen() {
            closeTeacherAvailabilityModal();
            // Open teacher screen in new tab/window
            window.open('teacher-screen.php', '_blank');
        }
        
        // Make functions globally accessible
        window.closeTeacherAvailabilityModal = closeTeacherAvailabilityModal;
        window.redirectToTeacherScreen = redirectToTeacherScreen;
        
        // Show teacher confirmation modal when teacher QR is scanned
        function showTeacherConfirmationModal(data) {
            console.log('Showing teacher confirmation modal for:', data.teacher.name);
            console.log('Action type:', data.action_type);
            console.log('Current status:', data.current_status);
            
            // Create teacher confirmation modal
            const modal = document.createElement('div');
            modal.className = 'ultra-simple-modal';
            modal.id = 'teacherConfirmationModal';
            
            // Determine modal styling and content based on action type
            const isMarkingAvailable = data.action_type === 'mark_available';
            const headerColor = isMarkingAvailable ? 'blue' : 'red';
            const actionIcon = isMarkingAvailable ? 'user-check' : 'user-times';
            const actionTitle = isMarkingAvailable ? 'Mark Yourself as Available?' : 'Mark Yourself as Unavailable?';
            const actionEmoji = isMarkingAvailable ? '🎯' : '🔴';
            
            // Current status display
            const currentStatusInfo = `
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-xl p-4 mb-4">
                    <div class="flex items-center justify-center space-x-2 mb-2">
                        <i class="fas fa-info-circle text-gray-600"></i>
                        <span class="text-sm font-semibold text-gray-800">Current Status</span>
                    </div>
                    <div class="text-center">
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full ${data.current_status === 'available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            <i class="fas fa-${data.current_status === 'available' ? 'check-circle' : 'times-circle'}"></i>
                            <span class="font-semibold">${data.current_status === 'available' ? 'Available' : 'Not Available'}</span>
                        </div>
                    </div>
                </div>
            `;
            
            // Pending requests info (only show for marking available)
            const pendingRequestsInfo = (isMarkingAvailable && data.pending_count > 0) ? `
                <div class="bg-gradient-to-r from-orange-50 to-orange-100 border border-orange-200 rounded-xl p-4 mb-4">
                    <div class="flex items-center justify-center space-x-2 mb-2">
                        <i class="fas fa-clock text-orange-600"></i>
                        <span class="text-sm font-semibold text-orange-800">Pending Consultation Requests</span>
                    </div>
                    <p class="text-xs sm:text-sm text-orange-700 mb-2">
                        You have <strong>${data.pending_count}</strong> pending consultation request(s) that will be automatically accepted if you confirm availability.
                    </p>
                    <div class="space-y-1">
                        ${data.pending_requests.map(req => `
                            <div class="text-xs text-orange-600 bg-orange-50 rounded px-2 py-1">
                                ${req.student_name} (${req.student_id}) - ${formatTimeAgo(req.request_time)}
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : '';
            
            // Warning for marking unavailable
            const unavailableWarning = !isMarkingAvailable ? `
                <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 border border-yellow-200 rounded-xl p-4 mb-4">
                    <div class="flex items-center justify-center space-x-2 mb-2">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        <span class="text-sm font-semibold text-yellow-800">Warning</span>
                    </div>
                    <p class="text-xs sm:text-sm text-yellow-700">
                        Marking yourself as unavailable will remove you from the consultation system. Students will no longer see you as available for consultation.
                    </p>
                </div>
            ` : '';
            
            const yesButtonText = isMarkingAvailable ? 'Yes, I\'m Available' : 'Yes, Make Me Unavailable';
            const noButtonText = isMarkingAvailable ? 'No, Stay Unavailable' : 'No, Stay Available';
            const yesButtonColor = isMarkingAvailable ? 'green' : 'red';
            
            let modalContent = `
                <div class="ultra-simple-content" id="teacherConfirmationModalContent">
                    <!-- Enhanced Modal Header -->
                    <div class="flex items-center justify-between p-4 sm:p-6 border-b border-${headerColor}-200 bg-gradient-to-r from-${headerColor}-50 to-${headerColor}-100 rounded-t-2xl">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-${headerColor}-400 to-${headerColor}-600 rounded-full flex items-center justify-center shadow-lg">
                                <i class="fas fa-${actionIcon} text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-xl font-bold text-gray-800">Confirm Status Change</h3>
                                <p class="text-xs sm:text-sm text-${headerColor}-600 font-medium">Teacher QR Code Verified</p>
                            </div>
                        </div>
                        <button onclick="closeTeacherConfirmationModal()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 rounded-full hover:bg-gray-100">
                            <i class="fas fa-times text-lg sm:text-xl"></i>
                        </button>
                    </div>

                    <!-- Enhanced Modal Body -->
                    <div class="p-4 sm:p-6">
                        <div class="text-center">
                            <div class="mb-6">
                                <div class="w-20 h-20 bg-gradient-to-br from-${headerColor}-400 to-${headerColor}-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg animate-pulse">
                                    <i class="fas fa-question-circle text-white text-3xl"></i>
                                </div>
                                <h4 class="text-xl sm:text-2xl font-bold text-gray-800 mb-3">${actionEmoji} ${actionTitle}</h4>
                                <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                                    Hello <strong>${data.teacher.name}</strong>!<br>
                                    ${data.message}
                                </p>
                                
                                ${currentStatusInfo}
                                ${pendingRequestsInfo}
                                ${unavailableWarning}
                                
                                <div class="flex items-center justify-center space-x-4 text-xs sm:text-sm text-gray-500">
                                    <span class="flex items-center">
                                        <i class="fas fa-qrcode mr-1"></i>
                                        QR Verified
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-clock mr-1"></i>
                                        Real-time
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        Secure
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Modal Footer -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 p-4 sm:p-6 border-t border-${headerColor}-200 bg-gradient-to-r from-${headerColor}-50 to-${headerColor}-100 rounded-b-2xl">
                        <button onclick="confirmTeacherAvailability(${data.teacher.id}, false, '${data.action_type}')"
                                class="flex-1 bg-gradient-to-r from-gray-500 to-gray-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-gray-600 hover:to-gray-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                            <i class="fas fa-times mr-2"></i>${noButtonText}
                        </button>
                        <button onclick="confirmTeacherAvailability(${data.teacher.id}, true, '${data.action_type}')"
                                class="flex-1 bg-gradient-to-r from-${yesButtonColor}-500 to-${yesButtonColor}-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-${yesButtonColor}-600 hover:to-${yesButtonColor}-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-${yesButtonColor}-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                            <i class="fas fa-check mr-2"></i>${yesButtonText}
                        </button>
                    </div>
                </div>
            `;
            
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
            
            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeTeacherConfirmationModal();
                }
            });

            // Close modal with Escape key
            const handleEscape = function(event) {
                if (event.key === 'Escape') {
                    closeTeacherConfirmationModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
            
            // Play notification sound
            try {
                const notificationAudio = new Audio('notification-sound.mp3');
                notificationAudio.volume = 0.3;
                notificationAudio.play().catch(e => console.log('Notification audio failed:', e));
            } catch (e) {
                console.log('Notification audio not supported:', e);
            }
        }
        
        // Close teacher confirmation modal
        function closeTeacherConfirmationModal() {
            const modal = document.getElementById('teacherConfirmationModal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = '';
                cleanupBlurEffects();
            }
            
            // Clear the input field for next scan
            const studentIdInput = document.getElementById('studentIdInput');
            if (studentIdInput) {
                setTimeout(() => {
                    studentIdInput.focus();
                }, 100);
            }
        }
        
        // Confirm teacher availability
        function confirmTeacherAvailability(teacherId, confirmed, actionType) {
            console.log('Confirming teacher availability:', teacherId, confirmed, actionType);
            
            // Close confirmation modal
            closeTeacherConfirmationModal();
            
            // Log processing status
            const actionText = actionType === 'mark_available' ? 'marking available' : 'marking unavailable';
            console.log(`Processing ${actionText}...`);
            
            // Send confirmation to API
            const formData = new FormData();
            formData.append('teacher_id', teacherId);
            formData.append('confirmed', confirmed ? 'true' : 'false');
            formData.append('action_type', actionType);
            
            fetch('../api/confirm-teacher-availability.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Confirmation response:', data);
                
                if (data.success) {
                    if (data.confirmed) {
                        if (data.action_type === 'mark_available') {
                            // Teacher confirmed availability - show success modal
                            showTeacherAvailabilityModal({
                                teacher: data.teacher,
                                marked_available: data.marked_available,
                                auto_accepted: data.auto_accepted_count > 0,
                                pending_request: data.accepted_requests && data.accepted_requests.length > 0 ? data.accepted_requests[0] : null,
                                accepted_requests: data.accepted_requests || []
                            });
                        } else if (data.action_type === 'mark_unavailable') {
                            // Teacher confirmed unavailability - log message
                            console.log('Teacher marked unavailable:', data.message);
                            
                            // Play success sound
                            try {
                                const successAudio = new Audio('notification-sound.mp3');
                                successAudio.volume = 0.3;
                                successAudio.play().catch(e => console.log('Success audio failed:', e));
                            } catch (e) {
                                console.log('Success audio not supported:', e);
                            }
                        }
                    } else {
                        // Teacher declined the action
                        console.log('Teacher declined action:', data.message);
                    }
                } else {
                    console.error('Confirmation failed:', data.error);
                }
            })
            .catch(error => {
                console.error('Confirmation error:', error);
            });
        }
        
        // Helper function to format time ago
        function formatTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diffInMinutes = Math.floor((now - time) / (1000 * 60));
            
            if (diffInMinutes < 1) return 'Just now';
            if (diffInMinutes === 1) return '1 minute ago';
            if (diffInMinutes < 60) return `${diffInMinutes} minutes ago`;
            
            const diffInHours = Math.floor(diffInMinutes / 60);
            if (diffInHours === 1) return '1 hour ago';
            if (diffInHours < 24) return `${diffInHours} hours ago`;
            
            return time.toLocaleDateString();
        }
        
        // Make functions globally accessible
        window.closeTeacherConfirmationModal = closeTeacherConfirmationModal;
        window.confirmTeacherAvailability = confirmTeacherAvailability;
        
        // Show pending request indicator
        function showPendingRequest(teacherName) {
            // Remove any existing pending indicator
            const existingIndicator = document.getElementById('pendingRequestIndicator');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            const pendingIndicator = document.createElement('div');
            pendingIndicator.id = 'pendingRequestIndicator';
            pendingIndicator.className = 'fixed bottom-6 right-6 z-50 p-6 rounded-2xl shadow-2xl bg-gradient-to-r from-blue-500 to-blue-600 text-white animate-pulse border border-blue-400 max-w-sm';
            pendingIndicator.innerHTML = `
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <i class="fas fa-paper-plane text-xl"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-lg mb-1">📤 Request Sent Successfully</h4>
                        <p class="text-sm text-blue-100 mb-3">Your consultation request has been sent to <strong>${teacherName}</strong>. Please wait for them to reach out to you.</p>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-blue-100">Waiting for teacher response...</span>
                            <div class="flex space-x-1">
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></div>
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" style="animation-delay: 0.1s;"></div>
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" style="animation-delay: 0.3s;"></div>
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                            </div>
                        </div>
                        <div class="text-xs text-blue-100">
                            <i class="fas fa-info-circle mr-1"></i>Teacher will contact you when ready
                        </div>
                        <div class="mt-3 text-center">
                            <div class="text-xs text-blue-100 mb-1">Teachers will become clickable in <span id="countdownTimer" class="font-bold">10</span> seconds</div>
                            <div class="w-full bg-blue-400 bg-opacity-30 rounded-full h-1">
                                <div id="countdownProgress" class="bg-white h-1 rounded-full transition-all duration-1000 ease-linear" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(pendingIndicator);
            
            // Disable all teacher cards while waiting for response
            const teacherCards = document.querySelectorAll('.teacher-card');
            teacherCards.forEach(card => {
                card.style.opacity = '0.5';
                card.style.pointerEvents = 'none';
                card.style.cursor = 'not-allowed';
            });
            
            // Start countdown timer
            let secondsLeft = 10;
            const countdownTimerElement = document.getElementById('countdownTimer');
            const countdownProgressElement = document.getElementById('countdownProgress');
            
            const countdownInterval = setInterval(() => {
                secondsLeft--;
                
                // Update countdown display
                if (countdownTimerElement) {
                    countdownTimerElement.textContent = secondsLeft;
                }
                
                // Update progress bar
                if (countdownProgressElement) {
                    const progressPercentage = (secondsLeft / 10) * 100;
                    countdownProgressElement.style.width = progressPercentage + '%';
                    
                    // Change color as time runs out
                    if (secondsLeft <= 3) {
                        countdownProgressElement.classList.remove('bg-white');
                        countdownProgressElement.classList.add('bg-red-300');
                    } else if (secondsLeft <= 5) {
                        countdownProgressElement.classList.remove('bg-white');
                        countdownProgressElement.classList.add('bg-yellow-300');
                    }
                }
                
                // Auto-hide when countdown reaches 0
                if (secondsLeft <= 0) {
                    clearInterval(countdownInterval);
                    hidePendingRequest();
                    // Re-enable teacher cards after hiding the pending request
                    setTimeout(() => {
                        console.log('✅ Re-enabling teacher cards after pending request auto-hide...');
                        reEnableTeacherCards();
                    }, 500); // Small delay to ensure pending request is fully hidden
                }
            }, 1000);
        }
        
        function hidePendingRequest() {
            const pendingIndicator = document.getElementById('pendingRequestIndicator');
            if (pendingIndicator) {
                pendingIndicator.remove();
            }
            
            // Re-enable all teacher cards
            reEnableTeacherCards();
        }
        
        // Function to re-enable teacher cards and make them clickable
        function reEnableTeacherCards() {
            console.log('🔄 Re-enabling teacher cards...');
            
            // Re-enable all teacher cards
            const teacherCards = document.querySelectorAll('.teacher-card');
            teacherCards.forEach(card => {
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
                card.style.cursor = 'pointer';
                
                // Remove any disabled styling
                card.classList.remove('opacity-50', 'cursor-not-allowed');
                card.classList.add('hover:scale-105', 'transition-transform');
                
                // Re-enable click events if they were disabled
                card.onclick = null; // Remove any disabled click handlers
            });
            
            // Show a brief notification that teachers are now clickable
            showEnhancedNotification('✅ Teachers are now available for new consultation requests!', 'success');
            
            console.log('✅ Teacher cards re-enabled successfully');
        }

        // Track user interaction to enable audio playback
        function enableAudioPlayback() {
            if (!window.userInteracted) {
                window.userInteracted = true;
                console.log('User interaction detected, audio playback enabled');
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeConsultationModal();
            }
        });
        
        // Test function accessibility
        console.log('Testing function accessibility:');
        console.log('closeConsultationModal:', typeof closeConsultationModal);
        console.log('stopResponseAudio:', typeof stopResponseAudio);
        console.log('tryAnotherTeacher:', typeof tryAnotherTeacher);
        console.log('window.closeConsultationModal:', typeof window.closeConsultationModal);
        
        // Add event listeners for user interaction
        document.addEventListener('click', enableAudioPlayback);
        document.addEventListener('keydown', enableAudioPlayback);
        document.addEventListener('touchstart', enableAudioPlayback);
        document.addEventListener('mousedown', enableAudioPlayback);

        // Ensure loading and notification states are hidden on page load
        window.addEventListener('load', function() {
            console.log('Page loaded');
            const loadingState = document.getElementById('loadingState');
            
            if (loadingState) {
                loadingState.style.display = 'none';
                console.log('Loading state hidden');
            }
            
            // Initialize teacher card listeners if teachers section is visible
            const teachersSection = document.getElementById('teachersSection');
            if (teachersSection && !teachersSection.classList.contains('hidden')) {
                console.log('Teachers section is visible, initializing listeners');
                setTimeout(() => {
                    initializeTeacherCardListeners();
                }, 100);
            }
            
            // Test if click events are working
            const testCard = document.querySelector('.teacher-card');
            if (testCard) {
                console.log('Test card found');
            } else {
                console.log('No test card found');
            }
        });

        // Nuclear cleanup function - removes ALL effects
        function cleanupBlurEffects() {
            // Remove from body
            document.body.style.backdropFilter = '';
            document.body.style.webkitBackdropFilter = '';
            document.body.style.filter = '';
            
            // Remove from html element
            document.documentElement.style.backdropFilter = '';
            document.documentElement.style.webkitBackdropFilter = '';
            document.documentElement.style.filter = '';
            
            // Remove from ALL elements
            const allElements = document.querySelectorAll('*');
            allElements.forEach(element => {
                element.style.backdropFilter = '';
                element.style.webkitBackdropFilter = '';
                element.style.filter = '';
            });
            
            // Force multiple repaints
            document.body.offsetHeight;
            document.documentElement.offsetHeight;
            
            // Add a style tag to override everything
            const style = document.createElement('style');
            style.id = 'nuclear-blur-removal';
            style.textContent = `
                * {
                    backdrop-filter: none !important;
                    -webkit-backdrop-filter: none !important;
                    filter: none !important;
                }
                body, html {
                    backdrop-filter: none !important;
                    -webkit-backdrop-filter: none !important;
                    filter: none !important;
                }
            `;
            
            // Remove existing style tag if it exists
            const existingStyle = document.getElementById('nuclear-blur-removal');
            if (existingStyle) {
                existingStyle.remove();
            }
            
            document.head.appendChild(style);
        }

        // Clean up blur effects when page is unloaded
        window.addEventListener('beforeunload', cleanupBlurEffects);
        window.addEventListener('unload', cleanupBlurEffects);

        // Also clean up on page visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                cleanupBlurEffects();
                stopTeacherUpdates(); // Stop updates when page is hidden
            } else if (document.visibilityState === 'visible') {
                // Restart updates when page becomes visible again
                const teachersSection = document.getElementById('teachersSection');
                const studentId = sessionStorage.getItem('currentStudentId');
                if (studentId && teachersSection && !teachersSection.classList.contains('hidden')) {
                    startTeacherUpdates();
                }
            }
        });
        
        // Stop updates when page is unloaded
        window.addEventListener('beforeunload', function() {
            stopTeacherUpdates();
        });
        
            } catch (error) {
                console.error('Error in DOMContentLoaded:', error);
            }
        });

        // Standby Mode Functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing Standby Mode');
            
            const standbyVideoContainer = document.getElementById('standbyVideoContainer');
            const standbyVideo = document.getElementById('standbyVideo');
            const standbyIndicator = document.getElementById('standbyIndicator');
            const standbyCountdown = document.getElementById('standbyCountdown');
            const countdownTimer = document.getElementById('countdownTimer');
            const fullscreenBtn = document.getElementById('fullscreenBtn');
            const studentIdInput = document.getElementById('studentIdInput');
            
            let standbyTimeout;
            let countdownInterval;
            let isStandbyActive = false;
            let lastActivityTime = Date.now();
            const STANDBY_DELAY = 30000; // 30 seconds of inactivity
            
            // Function to start standby mode
            function startStandbyMode() {
                if (isStandbyActive) return;
                
                console.log('Starting standby mode');
                isStandbyActive = true;
                
                // Hide countdown
                standbyCountdown.classList.remove('active');
                
                // Show standby video
                standbyVideoContainer.classList.add('active');
                standbyIndicator.classList.add('active');
                
                // Play video
                if (standbyVideo) {
                    standbyVideo.play().catch(e => {
                        console.log('Video autoplay failed:', e);
                    });
                }
                
                // Ensure student ID input maintains focus even in standby
                if (studentIdInput) {
                    // Set focus after a short delay to ensure it works
                    setTimeout(() => {
                        studentIdInput.focus();
                        console.log('Maintained focus on student ID input during standby');
                    }, 100);
                }
            }
            
            // Function to stop standby mode
            function stopStandbyMode() {
                if (!isStandbyActive) return;
                
                console.log('Stopping standby mode');
                isStandbyActive = false;
                
                // Hide standby video
                standbyVideoContainer.classList.remove('active');
                standbyIndicator.classList.remove('active');
                
                // Pause video
                if (standbyVideo) {
                    standbyVideo.pause();
                }
                
                // Ensure student ID input regains focus after standby
                if (studentIdInput) {
                    setTimeout(() => {
                        studentIdInput.focus();
                        console.log('Regained focus on student ID input after standby');
                    }, 100);
                }
            }
            
            // Function to reset activity timer
            function resetActivityTimer() {
                lastActivityTime = Date.now();
                
                // Clear existing timeout and countdown
                if (standbyTimeout) {
                    clearTimeout(standbyTimeout);
                }
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                }
                
                // Hide countdown
                standbyCountdown.classList.remove('active');
                
                // Stop standby mode if active
                if (isStandbyActive) {
                    stopStandbyMode();
                }
                
                // Ensure student ID input has focus when activity is detected
                if (studentIdInput) {
                    setTimeout(() => {
                        studentIdInput.focus();
                    }, 50);
                }
                
                // Set new timeout
                standbyTimeout = setTimeout(() => {
                    startStandbyMode();
                }, STANDBY_DELAY);
                
                // Start countdown 5 seconds before standby
                setTimeout(() => {
                    if (!isStandbyActive) {
                        startCountdown();
                    }
                }, STANDBY_DELAY - 5000);
            }
            
            // Function to start countdown
            function startCountdown() {
                if (isStandbyActive) return;
                
                let timeLeft = 30;
                standbyCountdown.classList.add('active');
                countdownTimer.textContent = timeLeft;
                
                countdownInterval = setInterval(() => {
                    timeLeft--;
                    countdownTimer.textContent = timeLeft;
                    
                    if (timeLeft <= 0 || isStandbyActive) {
                        clearInterval(countdownInterval);
                        standbyCountdown.classList.remove('active');
                    }
                }, 1000);
            }
            
            // Event listeners for user activity
            const activityEvents = [
                'mousemove',
                'mousedown',
                'mouseup',
                'click',
                'touchstart',
                'touchend',
                'touchmove',
                'keydown',
                'keyup',
                'scroll',
                'wheel'
            ];
            
            activityEvents.forEach(eventType => {
                document.addEventListener(eventType, resetActivityTimer, { passive: true });
            });
            
            // Start activity timer
            resetActivityTimer();
            
            // Continuous focus maintenance for student ID input
            function maintainFocus() {
                if (studentIdInput && document.activeElement !== studentIdInput) {
                    // Only refocus if the input is not already focused and is visible
                    if (studentIdInput.offsetParent !== null) { // Check if element is visible
                        studentIdInput.focus();
                        console.log('Maintained focus on student ID input');
                    }
                }
            }
            
            // Set up continuous focus checking
            setInterval(maintainFocus, 2000); // Check every 2 seconds
            
            // Additional focus events
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden && studentIdInput) {
                    setTimeout(() => {
                        studentIdInput.focus();
                    }, 100);
                }
            });
            
            // Focus on window focus
            window.addEventListener('focus', function() {
                if (studentIdInput) {
                    setTimeout(() => {
                        studentIdInput.focus();
                    }, 100);
                }
            });
            
            // Handle video click to exit standby
            standbyVideoContainer.addEventListener('click', function(e) {
                e.preventDefault();
                stopStandbyMode();
                resetActivityTimer();
            });
            
            // Handle video errors
            if (standbyVideo) {
                standbyVideo.addEventListener('error', function(e) {
                    console.error('Standby video error:', e);
                    // Fallback: just show the overlay without video
                    standbyVideo.style.display = 'none';
                });
                
                standbyVideo.addEventListener('loadeddata', function() {
                    console.log('Standby video loaded successfully');
                });
            }
            
            // Fullscreen functionality
            function enterFullscreen() {
                const element = document.documentElement;
                
                if (element.requestFullscreen) {
                    element.requestFullscreen();
                } else if (element.webkitRequestFullscreen) { // Safari
                    element.webkitRequestFullscreen();
                } else if (element.msRequestFullscreen) { // IE/Edge
                    element.msRequestFullscreen();
                } else if (element.mozRequestFullScreen) { // Firefox
                    element.mozRequestFullScreen();
                }
            }
            
            function exitFullscreen() {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) { // Safari
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) { // IE/Edge
                    document.msExitFullscreen();
                } else if (document.mozCancelFullScreen) { // Firefox
                    document.mozCancelFullScreen();
                }
            }
            
            function isFullscreen() {
                return !!(document.fullscreenElement || 
                         document.webkitFullscreenElement || 
                         document.msFullscreenElement || 
                         document.mozFullScreenElement);
            }
            
            // Fullscreen button click handler
            fullscreenBtn.addEventListener('click', function() {
                if (isFullscreen()) {
                    exitFullscreen();
                } else {
                    enterFullscreen();
                }
            });
            
            // Update fullscreen button icon and state
            function updateFullscreenButton() {
                const icon = fullscreenBtn.querySelector('i');
                if (isFullscreen()) {
                    fullscreenBtn.classList.add('fullscreen');
                    icon.className = 'fas fa-compress';
                    fullscreenBtn.title = 'Exit Fullscreen';
                } else {
                    fullscreenBtn.classList.remove('fullscreen');
                    icon.className = 'fas fa-expand';
                    fullscreenBtn.title = 'Enter Fullscreen';
                }
            }
            
            // Listen for fullscreen changes
            document.addEventListener('fullscreenchange', updateFullscreenButton);
            document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
            document.addEventListener('msfullscreenchange', updateFullscreenButton);
            document.addEventListener('mozfullscreenchange', updateFullscreenButton);
            
            // Keyboard shortcut to manually trigger standby mode (Ctrl+Shift+S)
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.key === 'S') {
                    e.preventDefault();
                    if (isStandbyActive) {
                        stopStandbyMode();
                        resetActivityTimer();
                    } else {
                        startStandbyMode();
                    }
                }
                
                // Fullscreen keyboard shortcut (F11)
                if (e.key === 'F11') {
                    e.preventDefault();
                    if (isFullscreen()) {
                        exitFullscreen();
                    } else {
                        enterFullscreen();
                    }
                }
            });
            
            console.log('Standby mode initialized');
        });
        
        // Add error handling
        window.addEventListener('error', function(e) {
            console.error('JavaScript error:', e.message, 'at', e.filename, 'line', e.lineno);
        });

        // =====================================================
        // CANVAS PARTICLE ANIMATION
        // =====================================================

        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            initCanvasAnimation();
        });

        function initCanvasAnimation() {
            var canvas = document.getElementById('canvas');
            if (!canvas) {
                console.error('Canvas element not found!');
                return;
            }

            var context = canvas.getContext('2d');
            if (!context) {
                console.error('Canvas context not available!');
                return;
            }

            // Set canvas size
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }

            // Initial resize
            resizeCanvas();

            // Resize on window resize
            window.addEventListener('resize', resizeCanvas);

            window.requestAnimFrame = function()
            {
                return (
                    window.requestAnimationFrame       || 
                    window.webkitRequestAnimationFrame || 
                    window.mozRequestAnimationFrame    || 
                    window.oRequestAnimationFrame      || 
                    window.msRequestAnimationFrame     || 
                    function(/* function */ callback){
                        window.setTimeout(callback, 1000 / 60);
                    }
                );
            }();

            //get DPI
            let dpi = window.devicePixelRatio || 1;
            context.scale(dpi, dpi);
            console.log('Canvas DPI:', dpi);

            function fix_dpi() {
                //get CSS height
                //the + prefix casts it to an integer
                //the slice method gets rid of "px"
                let style_height = +getComputedStyle(canvas).getPropertyValue("height").slice(0, -2);
                let style_width = +getComputedStyle(canvas).getPropertyValue("width").slice(0, -2);

                //scale the canvas
                canvas.setAttribute('height', style_height * dpi);
                canvas.setAttribute('width', style_width * dpi);
            }

            var particle_count = 70,
                particles = [],
                couleurs   = ["#3a0088", "#930077", "#e61c5d","#ffbd39"];
            
            function Particle()
            {
                this.radius = Math.round((Math.random()*2)+2);
                this.x = Math.floor((Math.random() * (canvas.width - this.radius * 2) + this.radius));
                this.y = Math.floor((Math.random() * (canvas.height - this.radius * 2) + this.radius));
                this.color = couleurs[Math.floor(Math.random()*couleurs.length)];
                this.speedx = Math.round((Math.random()*201)+0)/100;
                this.speedy = Math.round((Math.random()*201)+0)/100;

                switch (Math.round(Math.random()*couleurs.length))
                {
                    case 1:
                    this.speedx *= 1;
                    this.speedy *= 1;
                    break;
                    case 2:
                    this.speedx *= -1;
                    this.speedy *= 1;
                    break;
                    case 3:
                    this.speedx *= 1;
                    this.speedy *= -1;
                    break;
                    case 4:
                    this.speedx *= -1;
                    this.speedy *= -1;
                    break;
                }
                    
                this.move = function()
                {
                    context.beginPath();
                    context.globalCompositeOperation = 'source-over';
                    context.fillStyle   = this.color;
                    context.globalAlpha = 1;
                    context.arc(this.x, this.y, this.radius, 0, Math.PI*2, false);
                    context.fill();
                    context.closePath();

                    this.x = this.x + this.speedx;
                    this.y = this.y + this.speedy;
                    
                    if(this.x <= 0+this.radius)
                    {
                        this.speedx *= -1;
                    }
                    if(this.x >= canvas.width-this.radius)
                    {
                        this.speedx *= -1;
                    }
                    if(this.y <= 0+this.radius)
                    {
                        this.speedy *= -1;
                    }
                    if(this.y >= canvas.height-this.radius)
                    {
                        this.speedy *= -1;
                    }

                    for (var j = 0; j < particle_count; j++)
                    {
                        var particleActuelle = particles[j],
                            yd = particleActuelle.y - this.y,
                            xd = particleActuelle.x - this.x,
                            d  = Math.sqrt(xd * xd + yd * yd);

                        if ( d < 200 )
                        {
                            context.beginPath();
                            context.globalAlpha = (200 - d) / (200 - 0);
                            context.globalCompositeOperation = 'destination-over';
                            context.lineWidth = 1;
                            context.moveTo(this.x, this.y);
                            context.lineTo(particleActuelle.x, particleActuelle.y);
                            context.strokeStyle = this.color;
                            context.lineCap = "round";
                            context.stroke();
                            context.closePath();
                        }
                    }
                };
            };
            
            // Create particles
            for (var i = 0; i < particle_count; i++)
            {
                var particle = new Particle();
                particles.push(particle);
            }

            function animate()
            {
                context.clearRect(0, 0, canvas.width, canvas.height);
                for (var i = 0; i < particle_count; i++)
                {
                    particles[i].move();
                }
                requestAnimFrame(animate);
            }
            
            // Start animation
            animate();
            console.log('Canvas animation started with', particle_count, 'particles');
        }
        
        // Handle page visibility changes to continue status checking
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Page is visible again, check status immediately if we have an active session
                const currentSessionId = sessionStorage.getItem('currentSessionId');
                if (currentSessionId && isAutomaticStatusChecking) {
                    console.log('🔄 Page became visible, checking status immediately...');
                    // Trigger an immediate status check
                    setTimeout(() => {
                        if (isAutomaticStatusChecking) {
                            checkAutomaticStatus(currentSessionId, sessionStorage.getItem('currentTeacherName') || 'Teacher');
                        }
                    }, 500);
                }
            }
        });
        
        // Ensure status checking continues even when page is not visible
        // This is important for mobile devices and when switching tabs
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
        }
        
        console.log('✅ Student screen fully initialized with automatic status checking');
    </script>
</body>
</html>
