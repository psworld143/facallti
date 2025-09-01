<?php
session_start();
require_once '../config/database.php';

// Set page title
$page_title = 'Teacher Office Screen';

// Get selected department from URL parameter
$selected_department = $_GET['dept'] ?? '';

// If no department is specified, redirect to department selection
if (empty($selected_department)) {
    header('Location: index.php');
    exit();
}

// Get current day and time
$current_day = date('l'); // Monday, Tuesday, etc.
$current_time = date('H:i:s'); // Current time in HH:MM:SS format

// Get active semester and academic year
$semester_query = "SELECT name, academic_year FROM semesters WHERE status = 'active' LIMIT 1";
$semester_result = mysqli_query($conn, $semester_query);
$active_semester = null;
$active_academic_year = null;

if ($semester_result && mysqli_num_rows($semester_result) > 0) {
    $semester_row = mysqli_fetch_assoc($semester_result);
    $active_semester = $semester_row['name'];
    $active_academic_year = $semester_row['academic_year'];
}

// Get department information and all teachers with consultation hours today (both scanned and not scanned)
$department_query = "SELECT DISTINCT 
                    f.id,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position,
                    f.email,
                    f.bio,
                    f.image_url,
                    f.is_active,
                    ch.start_time,
                    ch.end_time,
                    ch.room,
                    ch.notes,
                    ta.scan_time,
                    ta.status as availability_status,
                    ta.last_activity
                   FROM faculty f 
                   INNER JOIN consultation_hours ch ON f.id = ch.teacher_id
                   LEFT JOIN teacher_availability ta ON f.id = ta.teacher_id AND ta.availability_date = CURDATE()
                   WHERE f.department = ? 
                   AND f.is_active = 1
                   AND ch.day_of_week = ?
                   AND ch.is_active = 1
                   AND ch.start_time <= ?
                   AND ch.end_time >= ?
                   " . ($active_semester ? "AND ch.semester = ?" : "") . "
                   " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                   AND f.id NOT IN (
                       SELECT teacher_id 
                       FROM consultation_leave 
                       WHERE leave_date = CURDATE()
                   )
                   GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.status, ta.last_activity
                   ORDER BY ta.status DESC, f.first_name, f.last_name";

$department_stmt = mysqli_prepare($conn, $department_query);
if ($department_stmt) {
    // Build parameter types and values dynamically
    $param_types = "ssss";
    $param_values = [$selected_department, $current_day, $current_time, $current_time];
    
    if ($active_semester) {
        $param_types .= "s";
        $param_values[] = $active_semester;
    }
    if ($active_academic_year) {
        $param_types .= "s";
        $param_values[] = $active_academic_year;
    }
    
    mysqli_stmt_bind_param($department_stmt, $param_types, ...$param_values);
    mysqli_stmt_execute($department_stmt);
    $department_result = mysqli_stmt_get_result($department_stmt);
} else {
    // Fallback to simple query if prepared statement fails
    $fallback_query = "SELECT 
                        f.id,
                        f.first_name,
                        f.last_name,
                        f.department,
                        f.position,
                        f.email,
                        f.bio,
                        f.image_url,
                        f.is_active
                       FROM faculty f 
                       WHERE f.department = ? AND f.is_active = 1
                       ORDER BY f.first_name, f.last_name";
    
    $fallback_stmt = mysqli_prepare($conn, $fallback_query);
    mysqli_stmt_bind_param($fallback_stmt, "s", $selected_department);
    mysqli_stmt_execute($fallback_stmt);
    $department_result = mysqli_stmt_get_result($fallback_stmt);
}

$department_teachers = [];
while ($row = mysqli_fetch_assoc($department_result)) {
    $department_teachers[] = $row;
}

// If no teachers found with consultation hours, no teachers will be displayed

// Get department info
$dept_info_query = "SELECT DISTINCT department FROM faculty WHERE department = ? LIMIT 1";
$dept_info_stmt = mysqli_prepare($conn, $dept_info_query);
mysqli_stmt_bind_param($dept_info_stmt, "s", $selected_department);
mysqli_stmt_execute($dept_info_stmt);
$dept_info_result = mysqli_stmt_get_result($dept_info_stmt);
$dept_info = mysqli_fetch_assoc($dept_info_result);

// Generate unique office session ID
$office_session_id = uniqid('office_', true);
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

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        .office-screen {
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow-x: hidden;
        }

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
            z-index: 10000;
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
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
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
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            z-index: 10001;
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
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 10001;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .standby-countdown.active {
            display: block;
            opacity: 1;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }

        .clock {
            font-size: 4rem;
            font-weight: 700;
            color: #374151;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }

        .date-display {
            color: #6b7280;
            font-weight: 500;
        }

        .status-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-available {
            background-color: #fb923c;
        }

        .status-busy {
            background-color: #fb923c;
            opacity: 0.6;
        }

        .status-offline {
            background-color: #fb923c;
            opacity: 0.3;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(251, 146, 60, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(251, 146, 60, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(251, 146, 60, 0);
            }
        }

        .notification-panel {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);
            z-index: 1000;
            display: none;
            max-width: 600px;
            width: 95%;
            border: 3px solid #FF6B35;
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1), 0 0 20px rgba(255, 107, 53, 0.3);
            }
            to {
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1), 0 0 30px rgba(255, 107, 53, 0.6);
            }
        }

        .notification-panel.show {
            display: block;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { 
                transform: translate(-50%, -50%) scale(0.8); 
                opacity: 0; 
                filter: blur(10px);
            }
            to { 
                transform: translate(-50%, -50%) scale(1); 
                opacity: 1; 
                filter: blur(0px);
            }
        }

        .consultation-request {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .consultation-request::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #fb923c;
        }

        .consultation-request:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            border-color: #fb923c;
        }



        .teacher-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .teacher-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #fb923c;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .teacher-card:hover::before {
            transform: scaleX(1);
        }

        .teacher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border-color: #fb923c;
        }

        .department-icon {
            background: transparent;
            border-radius: 0;
            width: auto;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: none;
            border: none;
        }

        .section-title {
            color: #1f2937;
            font-weight: 700;
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 60px;
            height: 3px;
            background: #fb923c;
            border-radius: 2px;
        }

        .clock {
            font-family: 'Courier New', monospace;
            font-size: 2rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .loading {
            display: none;
        }

        .loading.show {
            display: block;
        }

        /* Enhanced notification animations */
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .animate-bounce {
            animation: bounce 1s infinite;
        }

        .animate-ping {
            animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(-25%);
                animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
            }
            50% {
                transform: none;
                animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
            }
        }

        @keyframes ping {
            75%, 100% {
                transform: scale(2);
                opacity: 0;
            }
        }

        /* Enhanced button hover effects */
        .transform {
            transition-property: transform;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }

        .hover\:scale-105:hover {
            transform: scale(1.05);
        }

        /* Background overlay for better focus */
        .notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 999;
            display: none;
        }

        .notification-overlay.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Enhanced modal animations */
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px) scale(0.9);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }
        
        @keyframes attentionPulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.7);
            }
            50% {
                box-shadow: 0 0 0 20px rgba(255, 107, 53, 0);
            }
        }
        
        @keyframes glowPulse {
            0%, 100% {
                border-color: #FF6B35;
                box-shadow: 0 0 20px rgba(255, 107, 53, 0.5);
            }
            50% {
                border-color: #E55A2B;
                box-shadow: 0 0 30px rgba(255, 107, 53, 0.8);
            }
        }
        
        .modal-focus {
            animation: modalSlideIn 0.6s ease-out, attentionPulse 2s infinite, glowPulse 3s infinite;
        }
        
        .attention-grabber {
            animation: attentionPulse 1.5s infinite;
        }

        /* Responsive Design Enhancements */
        @media (max-width: 1024px) {
            .clock {
                font-size: 1.75rem;
            }
            
            .consultation-request {
                padding: 1.25rem;
            }
        }

        @media (max-width: 768px) {
            .office-screen {
                padding: 0;
            }

            .clock {
                font-size: 1.5rem;
            }

            .consultation-request {
                padding: 1rem;
                margin-bottom: 0.75rem;
            }

            .notification-panel {
                padding: 2rem;
                max-width: 95%;
                width: 90%;
            }

            /* Mobile Header Adjustments */
            .mobile-header {
                padding: 0.75rem 0;
            }

            .mobile-header h1 {
                font-size: 1.125rem;
                line-height: 1.4;
            }

            .mobile-header img {
                height: 2.5rem;
                width: auto;
            }

            /* Mobile Main Content */
            .mobile-main {
                padding: 1rem;
                flex-direction: column;
            }

            .mobile-main > div {
                width: 100%;
                max-width: none;
            }

            /* Mobile Grid Adjustments */
            .mobile-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            /* Mobile Text Adjustments */
            .mobile-text-3xl {
                font-size: 1.5rem;
            }

            .mobile-text-2xl {
                font-size: 1.25rem;
            }

            .mobile-text-xl {
                font-size: 1.125rem;
            }

            /* Mobile Spacing */
            .mobile-mb-8 {
                margin-bottom: 2rem;
            }

            .mobile-mb-4 {
                margin-bottom: 1rem;
            }

            .mobile-p-8 {
                padding: 1rem;
            }

            /* Mobile Icon Sizes */
            .mobile-icon-4xl {
                font-size: 2rem;
            }

            .mobile-icon-2xl {
                font-size: 1.5rem;
            }

            /* Mobile Button Adjustments */
            .mobile-btn {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }

            .mobile-btn-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            /* Mobile Status Indicators */
            .mobile-status-group {
                flex-direction: column;
                gap: 0.5rem;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .clock {
                font-size: 1.25rem;
            }

            .consultation-request {
                padding: 0.75rem;
            }

            .notification-panel {
                padding: 1.5rem;
                width: 95%;
            }

            .mobile-header h1 {
                font-size: 1rem;
            }

            .mobile-text-3xl {
                font-size: 1.25rem;
            }

            .mobile-text-2xl {
                font-size: 1.125rem;
            }

            .mobile-mb-8 {
                margin-bottom: 1.5rem;
            }

            .mobile-p-8 {
                padding: 0.75rem;
            }

            .mobile-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
        }

        /* Landscape Orientation Adjustments */
        @media (orientation: landscape) and (max-height: 600px) {
            .office-screen {
                min-height: auto;
            }

            .mobile-mb-8 {
                margin-bottom: 1rem;
            }

            .mobile-mb-4 {
                margin-bottom: 0.5rem;
            }

            .clock {
                font-size: 1.25rem;
            }

            .mobile-text-3xl {
                font-size: 1.25rem;
            }

            .mobile-text-2xl {
                font-size: 1.125rem;
            }

            .mobile-icon-4xl {
                font-size: 1.5rem;
            }

            .mobile-icon-2xl {
                font-size: 1.25rem;
            }
        }

        /* Tablet Portrait Adjustments */
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
            .mobile-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .mobile-text-3xl {
                font-size: 1.75rem;
            }

            .mobile-text-2xl {
                font-size: 1.5rem;
            }
        }

        /* Large Screen Optimizations */
        @media (min-width: 1440px) {
            .consultation-request {
                padding: 2rem;
            }

            .clock {
                font-size: 2.5rem;
            }

            .mobile-text-3xl {
                font-size: 2.5rem;
            }

            .mobile-text-2xl {
                font-size: 2rem;
            }
        }

        /* Enhanced Scrollbar for Better UX */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #fb923c, #f97316);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #f97316, #fb923c);
        }

        /* Touch Device Optimizations */
        @media (hover: none) and (pointer: coarse) {
            .hover\:scale-105:hover {
                transform: none;
            }

            .mobile-btn {
                min-height: 44px; /* Minimum touch target size */
            }

            .status-indicator {
                min-width: 24px;
                min-height: 24px;
            }
        }

        /* Fullscreen Button Styles */
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
        
        /* Fullscreen button always visible on teacher screen */
        .fullscreen-btn {
            opacity: 1;
            pointer-events: auto;
            transform: scale(1);
        }
    </style>
</head>
<body class="office-screen">
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

    <!-- Fullscreen Button -->
    <button id="fullscreenBtn" class="fullscreen-btn" title="Toggle Fullscreen">
        <i class="fas fa-expand"></i>
    </button>

    <!-- QR Scanner Modal Removed - Using form-only approach -->

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-8 mobile-main">
        <div class="main-container text-center text-gray-800 w-full max-w-6xl p-8">
            <!-- Clock and Date -->
            <div class="mb-6 sm:mb-8 mobile-mb-8">
                <div class="clock mb-2" id="clock"><?php echo date('H:i:s'); ?></div>
                <div class="date-display text-lg sm:text-xl" id="date"><?php echo date('l, F j, Y'); ?></div>
            </div>

            <!-- Department Monitor Screen -->
            <div class="mb-6 sm:mb-8 mobile-mb-8" id="standbyScreen">
                <div class="department-icon">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="w-12 h-12 sm:w-16 sm:h-16 object-contain drop-shadow-lg">
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold mb-2 mobile-text-3xl text-gray-800"><?php echo htmlspecialchars($selected_department); ?></h2>
                <p class="text-lg sm:text-xl mb-1 mobile-text-xl text-gray-700">Department Consultation Monitor</p>
                <p class="text-base sm:text-lg text-gray-600">
                    <?php echo count($department_teachers); ?> Active Teachers
                </p>
            </div>

            <!-- Available Teachers List -->
            <div class="mb-6 sm:mb-8 mobile-mb-8">
                <div class="consultation-request">
                    <h3 class="section-title text-xl sm:text-2xl font-bold mb-3 sm:mb-4 mobile-text-2xl">Available Teachers</h3>
                    

                        
                        <?php if (empty($department_teachers)): ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-clock text-gray-400 text-2xl"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-700 mb-2">No Teachers Available</h4>
                                <p class="text-gray-500">There are no teachers with consultation hours at this time.</p>
                                <p class="text-sm text-gray-400 mt-2">Please check back during consultation hours.</p>
                            </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mobile-grid">
                            <?php foreach ($department_teachers as $teacher): ?>
                                <?php
                                // Determine teacher availability status
                                $is_available = ($teacher['availability_status'] === 'available' && !empty($teacher['scan_time']));
                                $card_bg_class = $is_available ? 'bg-gradient-to-br from-green-50 to-green-100 border-green-300' : 'bg-gradient-to-br from-gray-50 to-gray-100 border-gray-300';
                                $status_text = $is_available ? 'Available' : 'Not Scanned';
                                $status_color = $is_available ? 'text-green-700' : 'text-gray-600';
                                $status_icon = $is_available ? 'fas fa-check-circle text-green-500' : 'fas fa-clock text-gray-500';
                                $avatar_border = $is_available ? 'border-green-500' : 'border-gray-400';
                                ?>
                        <div class="teacher-card <?php echo $card_bg_class; ?> border-2">
                            <div class="flex items-center space-x-2 sm:space-x-3 mb-2">
                                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-gray-200 to-gray-300 rounded-full flex items-center justify-center flex-shrink-0 border-2 <?php echo $avatar_border; ?>">
                    <?php if ($teacher['image_url']): ?>
                                        <img src="../<?php echo htmlspecialchars($teacher['image_url']); ?>" 
                                             alt="Teacher" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full object-cover">
                    <?php else: ?>
                                        <i class="fas fa-user text-gray-600 text-sm sm:text-base"></i>
                    <?php endif; ?>
                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-gray-800 font-semibold text-sm sm:text-base truncate">
                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </h4>
                                    <p class="text-gray-600 text-xs sm:text-sm truncate">
                    <?php echo htmlspecialchars($teacher['position']); ?>
                </p>
                                </div>
                            </div>
                            
                            <!-- Status Indicator -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <i class="<?php echo $status_icon; ?> text-xs sm:text-sm"></i>
                                    <span class="<?php echo $status_color; ?> text-xs sm:text-sm font-medium"><?php echo $status_text; ?></span>
                                </div>
                                <?php if ($is_available && $teacher['scan_time']): ?>
                                    <span class="text-xs text-green-600">
                                        <?php echo date('g:i A', strtotime($teacher['scan_time'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Consultation Hours -->
                            <?php if (isset($teacher['start_time']) && isset($teacher['end_time'])): ?>
                            <div class="mt-2 pt-2 border-t border-gray-200">
                                <div class="flex items-center text-xs text-gray-600">
                                    <i class="fas fa-clock mr-1"></i>
                                    <span>
                                        <?php 
                                        echo date('g:i A', strtotime($teacher['start_time'])) . ' - ' . 
                                             date('g:i A', strtotime($teacher['end_time']));
                                        if (isset($teacher['room']) && !empty($teacher['room'])) {
                                            echo ' | ' . htmlspecialchars($teacher['room']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                </div>
            </div>



            <!-- Status Message -->
            <div class="mb-6 sm:mb-8 mobile-mb-8">
                <div class="consultation-request">
                    <h3 class="text-xl sm:text-2xl font-bold mb-2 mobile-text-2xl text-gray-800">Monitor Status</h3>
                    <p class="text-base sm:text-lg text-gray-600 mb-3 sm:mb-4 mobile-text-xl">Department Monitor - Waiting for student consultation requests in <?php echo htmlspecialchars($selected_department); ?></p>
                    <div class="flex flex-col sm:flex-row items-center justify-center space-y-2 sm:space-y-0 sm:space-x-4 text-xs sm:text-sm mobile-status-group">
                        <span class="flex items-center" style="color: #fb923c;">
                            <i class="fas fa-clock mr-1 sm:mr-2"></i>
                            Monitoring Active
                        </span>
                        <?php
                        $available_count = 0;
                        $not_scanned_count = 0;
                        foreach ($department_teachers as $teacher) {
                            if ($teacher['availability_status'] === 'available' && !empty($teacher['scan_time'])) {
                                $available_count++;
                            } else {
                                $not_scanned_count++;
                            }
                        }
                        ?>
                        <span class="flex items-center text-green-600">
                            <i class="fas fa-check-circle mr-1 sm:mr-2"></i>
                            <?php echo $available_count; ?> available
                        </span>
                        <span class="flex items-center text-gray-600">
                            <i class="fas fa-clock mr-1 sm:mr-2"></i>
                            <?php echo $not_scanned_count; ?> not scanned
                        </span>
                    <span class="flex items-center" style="color: #fb923c;" id="pendingRequestsCount">
                        <i class="fas fa-clock mr-1 sm:mr-2"></i>
                        <span id="pendingCount"> 0 </span> pending requests
                        </span>
                        <span class="flex items-center text-gray-500 text-xs" id="lastRefreshTime">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Last updated: <span id="refreshTimeDisplay"><?php echo date('H:i:s'); ?></span>
                        </span>
                    </div>
                </div>
            </div>



            <!-- Office Controls -->
            <div class="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-4 mobile-btn-group">
                <button id="toggleStatusBtn" class="bg-orange-500 hover:bg-orange-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg transition-all duration-300 mobile-btn shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-toggle-on mr-1 sm:mr-2"></i>
                    Toggle Status
                </button>
                <button id="standbyToggleBtn" class="bg-purple-500 hover:bg-purple-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg transition-all duration-300 mobile-btn shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-video mr-1 sm:mr-2"></i>
                    Standby Mode
                </button>
                <button id="refreshBtn" class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg transition-all duration-300 mobile-btn shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-sync-alt mr-1 sm:mr-2"></i>
                    Refresh
                </button>
                <button onclick="testSound()" class="bg-green-500 hover:bg-green-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg transition-all duration-300 mobile-btn shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-volume-up mr-1 sm:mr-2"></i>
                    Test Sound
                </button>
            </div>

            <!-- Session Info -->
            <div class="mt-6 sm:mt-8 text-xs sm:text-sm text-gray-600 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg p-3 border border-gray-200 shadow-sm">
                <p class="mb-1">Session ID: <span class="font-mono text-xs text-gray-700"><?php echo $office_session_id; ?></span></p>
                <p class="mb-1">Last updated: <span id="lastUpdated" class="text-gray-700"><?php echo date('H:i:s'); ?></span></p>
                <p class="text-xs text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Auto-standby after 30s inactivity | Press <kbd class="bg-gray-200 px-1 py-0.5 rounded text-xs">V</kbd> to toggle manually
                </p>
            </div>
        </div>
    </main>

    <!-- Background Overlay -->
    <div id="notificationOverlay" class="notification-overlay"></div>
    
    <!-- Note: Old single-request notification removed - now using dynamic multiple-request system -->

    <!-- Loading State -->
    <div id="loadingState" class="loading fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg p-4 sm:p-6 flex items-center space-x-3 sm:space-x-4 mx-4 max-w-sm w-full">
            <div class="animate-spin rounded-full h-6 w-6 sm:h-8 sm:w-8 border-b-2 border-seait-orange"></div>
            <span class="text-gray-600 text-sm sm:text-base">Processing request...</span>
        </div>
    </div>

    <script>
        // Clock functionality
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            const dateString = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            document.getElementById('clock').textContent = timeString;
            document.getElementById('date').textContent = dateString;
            document.getElementById('lastUpdated').textContent = timeString;
        }

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock();

        // Status toggle functionality
        const toggleStatusBtn = document.getElementById('toggleStatusBtn');
        const statusIndicator = document.querySelector('.status-indicator');
        let isAvailable = true;

        toggleStatusBtn.addEventListener('click', function() {
            isAvailable = !isAvailable;
            
            if (isAvailable) {
                statusIndicator.className = 'status-indicator status-available';
                toggleStatusBtn.innerHTML = '<i class="fas fa-toggle-on mr-2"></i>Toggle Status';
                showNotification('Status changed to Available', 'info');
            } else {
                statusIndicator.className = 'status-indicator status-busy';
                toggleStatusBtn.innerHTML = '<i class="fas fa-toggle-off mr-2"></i>Toggle Status';
                showNotification('Status changed to Busy', 'info');
            }
        });

        // Refresh functionality (now uses AJAX)
        const refreshBtn = document.getElementById('refreshBtn');
        refreshBtn.addEventListener('click', function() {
            // Check for new requests immediately
            checkForConsultationRequests();
            
            // Also refresh the teacher list
            refreshTeacherList();
            
            showNotification('Refreshed consultation requests and teacher list', 'info');
        });

        // Notification functionality
        const notificationOverlay = document.getElementById('notificationOverlay');
        const loadingState = document.getElementById('loadingState');

        // Note: Old single-request handler removed - now using multiple-request system

        // Note: Old single-request event listeners removed - now using multiple-request system
        // The acceptRequest() and declineRequest() functions handle individual requests

        // Global audio variable for continuous sound
        let notificationAudio = null;
        let soundInterval = null;
        let userInteracted = false; // Track if user has interacted with page
        
        // Initialize sound enabled state
        window.soundEnabled = true; // Default to enabled
        
        // Play notification sound - simplified and more reliable
        function playNotificationSound() {
            console.log('Playing notification sound...');
            
            // Check if sound is disabled
            if (window.soundEnabled === false) {
                console.log('Sound is disabled, skipping audio notification');
                return;
            }
            
            // Stop any existing sound
            stopNotificationSound();
            
            // Force enable user interaction if not already enabled
            if (!userInteracted) {
                console.log('Enabling user interaction for audio...');
                userInteracted = true;
            }
            
            // Try to play the notification sound
            try {
                notificationAudio = new Audio('notification-sound.mp3');
                notificationAudio.volume = 0.7;
                
                notificationAudio.addEventListener('canplaythrough', () => {
                    console.log('Notification audio loaded successfully');
                });
                
                notificationAudio.addEventListener('error', (e) => {
                    console.log('Notification audio error:', e);
                    // Fallback to Web Audio
                    playWebAudioFallback();
                });
                
                const playPromise = notificationAudio.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log('Notification sound played successfully');
                        
                        // Set up continuous playback every 3 seconds
                        soundInterval = setInterval(() => {
                            if (notificationAudio && window.soundEnabled !== false) {
                                notificationAudio.currentTime = 0;
                                notificationAudio.play().catch(e => {
                                    console.log('Continuous audio playback failed:', e);
                                    playWebAudioFallback();
                                });
                            }
                        }, 3000);
                        
                        // Stop after 5 minutes
                        setTimeout(() => {
                            if (soundInterval) {
                                console.log('Stopping notification sound after 5 minutes');
                                stopNotificationSound();
                            }
                        }, 300000);
                        
                    }).catch(e => {
                        console.log('Notification sound failed:', e);
                        playWebAudioFallback();
                    });
                }
                
            } catch (e) {
                console.log('Notification sound creation failed:', e);
                playWebAudioFallback();
            }
        }
        
        // Enhanced Web Audio API fallback for remote access
        function playWebAudioFallback(type = 'notification') {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // Resume audio context if suspended (required for remote access)
                if (audioContext.state === 'suspended') {
                    audioContext.resume().then(() => {
                        console.log('Audio context resumed for remote access');
                        playWebAudioTone(audioContext, type);
                    });
                } else {
                    playWebAudioTone(audioContext, type);
                }
                
            } catch (e) {
                console.log('Web Audio fallback not supported:', e);
                // Ultimate fallback - try to use system notification
                if ('Notification' in window) {
                    requestNotificationPermission();
                }
            }
        }
        
        // Play Web Audio tone with enhanced patterns for remote access
        function playWebAudioTone(audioContext, type = 'notification') {
            try {
                // Enhanced notification pattern for better attention
                if (type === 'notification') {
                    // Play a sequence of tones
                    const frequencies = [800, 1000, 800, 1000];
                    const duration = 0.2;
                    let currentTime = audioContext.currentTime;
                    
                    frequencies.forEach((freq, index) => {
                        const osc = audioContext.createOscillator();
                        const gain = audioContext.createGain();
                        
                        osc.connect(gain);
                        gain.connect(audioContext.destination);
                        
                        osc.frequency.setValueAtTime(freq, currentTime);
                        gain.gain.setValueAtTime(0.3, currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, currentTime + duration);
                        
                        osc.start(currentTime);
                        osc.stop(currentTime + duration);
                        
                        currentTime += duration + 0.1; // Small gap between tones
                    });
                    
                    console.log('Enhanced Web Audio notification played');
                } else {
                    // Simple tone
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    const frequency = 800;
                    const duration = 0.5;
                    
                    oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime);
                    gainNode.gain.setValueAtTime(0.2, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + duration);
                    
                    console.log(`Web Audio tone played: ${frequency}Hz for ${duration}s`);
                }
                
            } catch (e) {
                console.log('Error playing Web Audio tone:', e);
            }
        }
        
        // Enable audio for remote access
        function enableAudioForRemoteAccess() {
            try {
                // Try to create and play a silent audio to unlock audio context
                const silentAudio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAAAAAAAAAAAAAAAAAAAZGF0YQAAAAA=');
                silentAudio.volume = 0.01;
                silentAudio.play().then(() => {
                    console.log('Silent audio played to unlock audio context');
                    userInteracted = true;
                }).catch(e => {
                    console.log('Silent audio failed:', e);
                });
                
                // Also try Web Audio API
                if (window.AudioContext || window.webkitAudioContext) {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    if (audioContext.state === 'suspended') {
                        audioContext.resume().then(() => {
                            console.log('Audio context unlocked for remote access');
                        });
                    }
                }
            } catch (e) {
                console.log('Failed to enable audio for remote access:', e);
            }
        }
        
        // Request notification permission for ultimate fallback
        function requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        console.log('Notification permission granted');
                        showSystemNotification('New consultation request received!');
                    }
                });
            } else if (Notification.permission === 'granted') {
                showSystemNotification('New consultation request received!');
            }
        }
        
        // Show system notification
        function showSystemNotification(message) {
            if ('Notification' in window && Notification.permission === 'granted') {
                const notification = new Notification('SEAIT Consultation System', {
                    body: message,
                    icon: '../assets/images/seait-logo.png',
                    badge: '../assets/images/seait-logo.png',
                    requireInteraction: true,
                    silent: false
                });
                
                // Auto close after 10 seconds
                setTimeout(() => {
                    notification.close();
                }, 10000);
                
                console.log('System notification shown');
            }
        }
        
        // Fallback sound using base64 encoded beep
        function playFallbackSound() {
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
                audio.volume = 0.3;
                audio.play().catch(e => {
                    console.log('Fallback sound failed:', e);
                });
            } catch (e) {
                console.log('Fallback sound not supported:', e);
            }
        }
        
        // Test audio functionality
        function testAudio() {
            console.log('Testing audio functionality...');
            console.log('User interacted:', userInteracted);
            
            // Test MP3 file
            const testAudio = new Audio('notification-sound.mp3');
            testAudio.volume = 0.1;
            
            testAudio.addEventListener('loadstart', () => console.log('Audio loading started'));
            testAudio.addEventListener('canplay', () => console.log('Audio can play'));
            testAudio.addEventListener('canplaythrough', () => console.log('Audio can play through'));
            testAudio.addEventListener('error', (e) => console.log('Audio error:', e));
            
            testAudio.play().then(() => {
                console.log('Test audio played successfully');
                testAudio.pause();
            }).catch(e => {
                console.log('Test audio failed:', e);
            });
        }
        
        // Add sound toggle button only
        function addTestButton() {
            // Add sound toggle button - default to ON
            const toggleBtn = document.createElement('button');
            toggleBtn.textContent = '🔊 Sound On';
            toggleBtn.className = 'fixed bottom-4 left-4 bg-orange-500 text-white px-4 py-2 rounded-lg z-50 shadow-lg hover:bg-orange-600 transition-colors';
            toggleBtn.onclick = toggleSound;
            toggleBtn.id = 'soundToggle';
            toggleBtn.style.position = 'fixed';
            toggleBtn.style.bottom = '1rem';
            toggleBtn.style.left = '1rem';
            toggleBtn.style.zIndex = '9999';
            document.body.appendChild(toggleBtn);
        }
        
        // Toggle sound on/off
        function toggleSound() {
            const toggleBtn = document.getElementById('soundToggle');
            if (toggleBtn.textContent.includes('On')) {
                // Turn sound OFF
                toggleBtn.textContent = '🔇 Sound Off';
                toggleBtn.className = 'fixed bottom-4 left-4 bg-gray-500 text-white px-4 py-2 rounded-lg z-50 shadow-lg hover:bg-gray-600 transition-colors';
                // Maintain fixed positioning
                toggleBtn.style.position = 'fixed';
                toggleBtn.style.bottom = '1rem';
                toggleBtn.style.left = '1rem';
                toggleBtn.style.zIndex = '9999';
                window.soundEnabled = false;
                stopNotificationSound();
                console.log('🔇 Sound disabled');
            } else {
                // Turn sound ON
                toggleBtn.textContent = '🔊 Sound On';
                toggleBtn.className = 'fixed bottom-4 left-4 bg-orange-500 text-white px-4 py-2 rounded-lg z-50 shadow-lg hover:bg-orange-600 transition-colors';
                // Maintain fixed positioning
                toggleBtn.style.position = 'fixed';
                toggleBtn.style.bottom = '1rem';
                toggleBtn.style.left = '1rem';
                toggleBtn.style.zIndex = '9999';
                window.soundEnabled = true;
                console.log('🔊 Sound enabled');
            }
        }
        
        // Stop notification sound
        function stopNotificationSound() {
            if (notificationAudio) {
                notificationAudio.pause();
                notificationAudio.currentTime = 0;
                notificationAudio = null;
            }
            
            if (soundInterval) {
                clearInterval(soundInterval);
                soundInterval = null;
            }
        }
        
        // Test sound function for debugging
        function testSound() {
            console.log('Testing sound...');
            console.log('User interacted:', userInteracted);
            console.log('Sound enabled:', window.soundEnabled);
            
            // Force enable user interaction
            userInteracted = true;
            
            // Try to play a simple test sound
            try {
                const testAudio = new Audio('notification-sound.mp3');
                testAudio.volume = 0.5;
                
                testAudio.addEventListener('canplay', () => console.log('Audio can play'));
                testAudio.addEventListener('canplaythrough', () => console.log('Audio can play through'));
                testAudio.addEventListener('error', (e) => console.log('Audio error:', e));
                
                testAudio.play().then(() => {
                    console.log('Test audio played successfully');
                    showEnhancedNotification('🔊 Test sound played successfully!', 'success');
                }).catch(e => {
                    console.log('Test audio failed:', e);
                    showEnhancedNotification('❌ Test sound failed: ' + e.message, 'error');
                    
                    // Try Web Audio fallback
                    playWebAudioFallback('notification');
                });
            } catch (e) {
                console.log('Test audio creation failed:', e);
                showEnhancedNotification('❌ Test sound creation failed: ' + e.message, 'error');
            }
        }
        
        // Enhanced notification function - DISABLED (upper right notifications removed)
        function showEnhancedNotification(message, type = 'info') {
            // Log to console instead of showing upper right notification
            console.log(`[${type.toUpperCase()}] ${message}`);
            
            // Optionally, you can uncomment the code below to move notifications to bottom center
            /*
            const notification = document.createElement('div');
            let bgColor = 'orange';
            let icon = 'info-circle';
            
            // Use orange for all notification types
            if (type === 'success' || type === 'warning' || type === 'error' || type === 'info') {
                bgColor = 'orange';
                icon = 'info-circle';
            }
            
            // Changed from top-4 right-4 to bottom-4 left-1/2 transform -translate-x-1/2 (bottom center)
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
            */
        }
        
        // Original notification function (for backward compatibility)
        function showNotification(message, type = 'info') {
            showEnhancedNotification(message, type);
        }

        // Standby mode - no automatic simulation
        // Consultation requests will only come from actual student selections

        // Global variables for request queue
        let requestQueue = [];
        let isModalOpen = false;
        let shownRequestIds = new Set(); // Track requests that have already been shown

        // Check for real consultation requests via AJAX
        function checkForConsultationRequests() {
            const department = '<?php echo htmlspecialchars($selected_department); ?>';
            

            
            fetch(`check-consultation-requests.php?dept=${encodeURIComponent(department)}`)
                .then(response => response.json())
                .then(data => {

                    
                    if (data.has_request) {
                        console.log(`Found ${data.total_requests} consultation request(s)`);
                        
                        // Update pending count
                        const pendingCountElement = document.getElementById('pendingCount');
                        if (pendingCountElement) {
                            pendingCountElement.textContent = data.total_requests;
                        }
                        
                        // Update request queue with new requests
                        updateRequestQueue(data.requests);
                    } else {
                        // No pending requests
                        const pendingCountElement = document.getElementById('pendingCount');
                        if (pendingCountElement) {
                            pendingCountElement.textContent = '0';
                        }
                        
                        // Clear queue and close modal only if no modal is currently open
                        if (requestQueue.length > 0 && !isModalOpen) {
                            requestQueue = [];
                            shownRequestIds.clear();
                            closeConsultationModal();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking for consultation requests:', error);
                    // Don't show alerts for AJAX errors
                });
        }

        // Update request queue and show next request if modal is closed
        function updateRequestQueue(newRequests) {
            let hasNewRequests = false;
            
            // Add new requests to queue (avoid duplicates)
            newRequests.forEach(newRequest => {
                const exists = requestQueue.find(req => req.request_id === newRequest.request_id);
                if (!exists) {
                    requestQueue.push(newRequest);
                    hasNewRequests = true;
                }
            });
            
            // Remove completed requests from queue
            requestQueue = requestQueue.filter(req => 
                newRequests.find(newReq => newReq.request_id === req.request_id)
            );
            
            // Only play sound and show notifications for truly new requests
            if (hasNewRequests) {
                playNotificationSound();
                showEnhancedNotification(`📋 ${newRequests.length} consultation request(s) received!`, 'success');
            }
            
            // Show next request if modal is not open
            if (!isModalOpen && requestQueue.length > 0) {
                showNextRequest();
            }
        }
        
        // Show the next request in the queue
        function showNextRequest() {
            if (requestQueue.length === 0 || isModalOpen) {
                return;
            }
            
            const request = requestQueue[0];
            
            // Check if this request has already been shown
            if (shownRequestIds.has(request.request_id)) {
                return;
            }
            
            shownRequestIds.add(request.request_id); // Mark as shown
            showConsultationModal(request);
        }
        
        // Show consultation modal for single request
        function showConsultationModal(request) {
            if (isModalOpen) {
                return; // Don't show if modal is already open
            }
            
            isModalOpen = true;
            
            // Remove any existing modal
            const existingModal = document.getElementById('consultationModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create modal container with enhanced focus
            const modalContainer = document.createElement('div');
            modalContainer.id = 'consultationModal';
            modalContainer.className = 'fixed inset-0 z-50 flex items-center justify-center';
            
            const requestTime = new Date(request.request_time).toLocaleTimeString();
            const queueInfo = requestQueue.length > 1 ? ` (${requestQueue.length} more in queue)` : '';
            
            modalContainer.innerHTML = `
                <!-- Phone Call Backdrop -->
                <div class="fixed inset-0 bg-gradient-to-br from-gray-900 via-black to-gray-900 backdrop-blur-lg flex items-center justify-center p-4"></div>
                
                <!-- Phone Call Interface -->
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl shadow-2xl max-w-4xl w-11/12 mx-auto relative transform transition-all duration-500 modal-focus border border-gray-600 overflow-hidden">
                    
                    <!-- Phone Call Header -->
                    <div class="bg-gradient-to-r from-green-500 to-green-600 p-4 text-center relative">
                        <div class="absolute top-2 right-2">
                            <button onclick="closeConsultationModal()" class="text-white hover:text-gray-200 text-xl transition-colors bg-black bg-opacity-20 rounded-full w-8 h-8 flex items-center justify-center">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                        <p class="text-white text-sm font-medium opacity-90">Consultation Request</p>
                        <div class="flex items-center justify-center mt-1">
                            <div class="w-2 h-2 bg-white rounded-full animate-pulse mr-2"></div>
                            <p class="text-white text-xs">Incoming</p>
                        </div>
                    </div>
                    
                    <!-- Teacher Photo Section -->
                    <div class="p-6 sm:p-8 bg-gray-800">
                        <div class="flex flex-col lg:flex-row items-center lg:items-start gap-6 lg:gap-8">
                            <!-- Left Side - Massive Teacher Photo (3/4 width) -->
                            <div class="w-full lg:w-3/4 flex justify-center">
                                <div class="relative">
                                    <!-- Teacher Photo - Massive Size -->
                                    <div class="w-64 h-64 sm:w-80 sm:h-80 lg:w-96 lg:h-96 xl:w-[28rem] xl:h-[28rem] mx-auto rounded-full overflow-hidden border-8 border-green-500 shadow-2xl animate-pulse">
                                        ${request.teacher_image_url ? 
                                            `<img src="../${request.teacher_image_url}" alt="${request.teacher_name}" class="w-full h-full object-cover">` :
                                            `<div class="w-full h-full bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-400 text-8xl sm:text-9xl lg:text-[10rem] xl:text-[12rem]"></i>
                                            </div>`
                                        }
                                    </div>
                                    
                                    <!-- Pulsing Ring Animation - Larger -->
                                    <div class="absolute inset-0 rounded-full border-8 border-green-500 opacity-20 animate-ping"></div>
                                    <div class="absolute inset-4 rounded-full border-4 border-green-400 opacity-30 animate-ping" style="animation-delay: 0.5s;"></div>
                                    <div class="absolute inset-8 rounded-full border-2 border-green-300 opacity-40 animate-ping" style="animation-delay: 1s;"></div>
                                </div>
                            </div>
                            
                            <!-- Right Side - Compact Information (1/4 width) -->
                            <div class="w-full lg:w-1/4 text-center lg:text-left">
                                <!-- Teacher Name -->
                                <h2 class="text-white text-xl sm:text-2xl lg:text-3xl font-bold mb-2">${request.teacher_name}</h2>
                                <p class="text-gray-300 text-xs sm:text-sm lg:text-base mb-4">Requested for consultation</p>
                                
                                <!-- Student Name -->
                                <div class="bg-gray-700 bg-opacity-50 rounded-xl p-3 sm:p-4 mb-4 border border-gray-600">
                                    <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Student Name</p>
                                    <p class="text-white text-lg sm:text-xl lg:text-2xl font-semibold">${request.student_name}</p>
                                </div>
                                
                                <!-- Request Time & Duration - Stacked on large screens -->
                                <div class="grid grid-cols-1 lg:grid-cols-1 gap-3">
                                    <div class="bg-gray-700 bg-opacity-30 rounded-lg p-3 text-center">
                                        <p class="text-gray-400 text-xs">Request Time</p>
                                        <p class="text-white text-sm font-medium">${requestTime}</p>
                                    </div>
                                    <div class="bg-gray-700 bg-opacity-30 rounded-lg p-3 text-center">
                                        <p class="text-gray-400 text-xs">Waiting Duration</p>
                                        <p id="waitTimeCounter" class="text-green-400 text-sm font-medium" data-start-time="${request.request_time}">${request.minutes_ago}m</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Queue Info (if applicable) -->
                    ${requestQueue.length > 1 ? `
                        <div class="bg-yellow-600 bg-opacity-20 border-t border-yellow-500 p-3 text-center">
                            <p class="text-yellow-300 text-xs font-medium">
                                <i class="fas fa-clock mr-1"></i>
                                ${requestQueue.length - 1} more request(s) in queue
                            </p>
                        </div>
                    ` : ''}
                    
                    <!-- Bottom Info with Auto-Dismiss Countdown -->
                    <div class="bg-gray-900 p-4 text-center border-t border-gray-700">
                        <p class="text-gray-400 text-xs mb-2">
                            <i class="fas fa-volume-up mr-1 animate-pulse"></i>
                            Notification will continue until dismissed
                        </p>
                        <div id="autoDismissCountdown" class="mb-2">
                            <p class="text-yellow-400 text-xs font-medium">
                                <i class="fas fa-clock mr-1"></i>
                                Auto-dismiss in <span id="countdownSeconds" class="font-mono">10</span>s
                            </p>
                            <div class="w-full bg-gray-700 rounded-full h-1 mt-1">
                                <div id="countdownProgress" class="bg-yellow-400 h-1 rounded-full transition-all duration-1000 ease-linear" style="width: 100%"></div>
                            </div>
                        </div>
                        <p class="text-gray-500 text-xs">
                            Press <kbd class="bg-gray-700 text-gray-300 px-2 py-1 rounded text-xs">ESC</kbd> to close
                        </p>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modalContainer);
            
            // Play notification sound
            playNotificationSound();
            
            // Start the countdown timer (with error handling)
            try {
                startWaitTimeCounter();
            } catch (error) {
                console.error('Timer error:', error);
                // Continue with modal display even if timer fails
            }
            
            // Show enhanced notification
            showEnhancedNotification(`🎯 New consultation request from ${request.student_name}!`, 'info');
            
            // Add focus effect to modal
            setTimeout(() => {
                const modal = document.getElementById('consultationModal');
                if (modal) {
                    const modalContent = modal.querySelector('.modal-focus');
                    if (modalContent) {
                        modalContent.classList.remove('modal-focus');
                        modalContent.classList.add('attention-grabber');
                    }
                }
            }, 3000);
            
            // Start auto-dismiss countdown if no other requests in queue
            startAutoDismissCountdown();
        }
        
        // Start auto-dismiss countdown
        function startAutoDismissCountdown() {
            // Only start countdown if no other requests are in queue
            if (requestQueue.length > 1) {
                // Hide countdown display if there are other requests
                const countdownElement = document.getElementById('autoDismissCountdown');
                if (countdownElement) {
                    countdownElement.style.display = 'none';
                }
                return;
            }
            
            let secondsLeft = 10;
            const countdownSecondsElement = document.getElementById('countdownSeconds');
            const countdownProgressElement = document.getElementById('countdownProgress');
            const countdownElement = document.getElementById('autoDismissCountdown');
            
            if (!countdownSecondsElement || !countdownProgressElement || !countdownElement) {
                console.warn('Countdown elements not found, proceeding with basic auto-dismiss');
                setTimeout(() => {
                    if (isModalOpen && requestQueue.length <= 1) {
                        console.log('Auto-dismissing modal after 10 seconds - no other requests in queue');
                        
                        // Remove the current request from queue and mark in database
                        if (requestQueue.length > 0) {
                            const currentRequest = requestQueue[0];
                            requestQueue = requestQueue.filter(req => req.request_id !== currentRequest.request_id);
                            shownRequestIds.delete(currentRequest.request_id);
                            // Update request status to accepted in database
                            updateRequestStatusToAccepted(currentRequest.request_id);
                        }
                        
                        closeConsultationModal(true); // Skip showing next request
                        showEnhancedNotification('✅ Consultation request auto-accepted after 10 seconds', 'info');
                    }
                }, 10000);
                return;
            }
            
            // Show countdown display
            countdownElement.style.display = 'block';
            
            const countdownInterval = setInterval(() => {
                secondsLeft--;
                
                // Update countdown display
                countdownSecondsElement.textContent = secondsLeft;
                
                // Update progress bar (width decreases as time runs out)
                const progressPercentage = (secondsLeft / 10) * 100;
                countdownProgressElement.style.width = progressPercentage + '%';
                
                // Change color as time runs out
                if (secondsLeft <= 3) {
                    countdownProgressElement.classList.remove('bg-yellow-400');
                    countdownProgressElement.classList.add('bg-red-500');
                    countdownSecondsElement.parentElement.classList.remove('text-yellow-400');
                    countdownSecondsElement.parentElement.classList.add('text-red-400');
                } else if (secondsLeft <= 5) {
                    countdownProgressElement.classList.remove('bg-yellow-400');
                    countdownProgressElement.classList.add('bg-orange-500');
                    countdownSecondsElement.parentElement.classList.remove('text-yellow-400');
                    countdownSecondsElement.parentElement.classList.add('text-orange-400');
                }
                
                // Check if modal should be dismissed
                if (secondsLeft <= 0) {
                    clearInterval(countdownInterval);
                    
                    // Only dismiss if modal is still open and no other requests
                    if (isModalOpen && requestQueue.length <= 1) {
                        console.log('Auto-dismissing modal after countdown - no other requests in queue');
                        
                        // Remove the current request from queue and mark in database
                        if (requestQueue.length > 0) {
                            const currentRequest = requestQueue[0];
                            requestQueue = requestQueue.filter(req => req.request_id !== currentRequest.request_id);
                            shownRequestIds.delete(currentRequest.request_id);
                            // Update request status to accepted in database
                            updateRequestStatusToAccepted(currentRequest.request_id);
                        }
                        
                        closeConsultationModal(true); // Skip showing next request
                        showEnhancedNotification('✅ Consultation request auto-accepted after 10 seconds', 'info');
                    }
                }
                
                // Stop countdown if modal is closed or new requests arrive
                if (!isModalOpen || requestQueue.length > 1) {
                    clearInterval(countdownInterval);
                    console.log('Stopping auto-dismiss countdown - modal closed or new requests arrived');
                }
                
            }, 1000); // Update every second
            
            // Store interval ID for cleanup
            if (countdownElement) {
                countdownElement.setAttribute('data-countdown-interval', countdownInterval);
            }
        }
        
        // Start wait time counter
        function startWaitTimeCounter() {
            const counterElement = document.getElementById('waitTimeCounter');
            if (!counterElement) {
                console.warn('Wait time counter element not found');
                return;
            }
            
            const startTimeString = counterElement.getAttribute('data-start-time');
            if (!startTimeString) {
                console.warn('No start time found for wait counter');
                return;
            }
            
            const startTime = new Date(startTimeString);
            if (isNaN(startTime.getTime())) {
                console.warn('Invalid start time format:', startTimeString);
                return;
            }
            
            // Get the initial minutes from the server
            const initialMinutes = parseInt(counterElement.textContent.match(/(\d+)/)[0]) || 0;
            
            // Start counting from now, but add the initial minutes
            let totalSeconds = initialMinutes * 60;
            
            function updateCounter() {
                try {
                    // Increment total seconds
                    totalSeconds++;
                    
                    const minutes = Math.floor(totalSeconds / 60);
                    const seconds = totalSeconds % 60;
                    
                    let timeText;
                    if (minutes === 0) {
                        timeText = `${seconds} seconds`;
                    } else if (minutes === 1) {
                        timeText = `${minutes} minute, ${seconds} seconds`;
                    } else {
                        timeText = `${minutes} minutes, ${seconds} seconds`;
                    }
                    
                    counterElement.textContent = timeText;
                    
                    // Add visual emphasis for longer wait times
                    if (minutes >= 5) {
                        counterElement.classList.add('text-red-600', 'animate-pulse');
                    } else if (minutes >= 3) {
                        counterElement.classList.add('text-orange-600');
                    }
                } catch (error) {
                    console.error('Error updating counter:', error);
                }
            }
            
            // Update immediately
            updateCounter();
            
            // Update every second
            const timerInterval = setInterval(updateCounter, 1000);
            
            // Store interval ID for cleanup
            counterElement.setAttribute('data-timer-interval', timerInterval);
        }
        
        // Stop wait time counter
        function stopWaitTimeCounter() {
            const counterElement = document.getElementById('waitTimeCounter');
            if (counterElement) {
                const intervalId = counterElement.getAttribute('data-timer-interval');
                if (intervalId) {
                    clearInterval(parseInt(intervalId));
                    counterElement.removeAttribute('data-timer-interval');
                }
            }
        }
        
        // Stop auto-dismiss countdown
        function stopAutoDismissCountdown() {
            const countdownElement = document.getElementById('autoDismissCountdown');
            if (countdownElement) {
                const intervalId = countdownElement.getAttribute('data-countdown-interval');
                if (intervalId) {
                    clearInterval(parseInt(intervalId));
                    countdownElement.removeAttribute('data-countdown-interval');
                }
            }
        }
        
        // Update request status to 'accepted' in database
        function updateRequestStatusToAccepted(requestId) {
            const formData = new FormData();
            formData.append('request_id', requestId);
            
            fetch('update-request-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to update request status:', data.error);
                }
            })
            .catch(error => {
                console.error('Error updating request status:', error);
            });
        }
        
        // Close consultation modal
        function closeConsultationModal(skipNextRequest = false) {
            // Stop the notification sound when modal is closed
            stopNotificationSound();
            
            // Stop the wait time counter
            stopWaitTimeCounter();
            
            // Stop the auto-dismiss countdown
            stopAutoDismissCountdown();
            
            const modal = document.getElementById('consultationModal');
            if (modal) {
                modal.remove();
            }
            isModalOpen = false;
            
            // Show next request if available (unless explicitly skipped for auto-dismiss)
            if (!skipNextRequest) {
                setTimeout(() => {
                    showNextRequest();
                }, 500);
            }
        }
        
        // Accept specific request
        function acceptRequest(requestId, teacherId, studentName) {
            // Stop the notification sound immediately when user interacts
            stopNotificationSound();
            
            // Get the current duration from wait time counter before stopping it
            const counterElement = document.getElementById('waitTimeCounter');
            let durationSeconds = 0;
            
            if (counterElement) {
                // Extract duration from the counter text (e.g., "5 minutes, 30 seconds")
                const timeText = counterElement.textContent;
                const minutesMatch = timeText.match(/(\d+)\s*minutes?/);
                const secondsMatch = timeText.match(/(\d+)\s*seconds?/);
                
                const minutes = minutesMatch ? parseInt(minutesMatch[1]) : 0;
                const seconds = secondsMatch ? parseInt(secondsMatch[1]) : 0;
                
                durationSeconds = (minutes * 60) + seconds;
            }
            
            // Stop the wait time counter
            stopWaitTimeCounter();
            
            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('action', 'accept');
            formData.append('teacher_id', teacherId);
            formData.append('duration_seconds', durationSeconds);
            
            fetch('respond-to-consultation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showEnhancedNotification(`✅ Accepted consultation request from ${studentName}!`, 'info');
                    
                    // Remove the processed request from queue and shown tracking
                    requestQueue = requestQueue.filter(req => req.request_id !== requestId);
                    shownRequestIds.delete(requestId);
                    
                    // Close current modal
                    closeConsultationModal();
                    
                    // Update pending count
                    const pendingCountElement = document.getElementById('pendingCount');
                    if (pendingCountElement) {
                        pendingCountElement.textContent = requestQueue.length;
                    }
                    
                    // Log the response for debugging
                    console.log('Consultation accepted successfully:', data);
                } else {
                    showEnhancedNotification(data.error || 'Failed to accept request', 'info');
                }
            })
            .catch(error => {
                console.error('Error accepting consultation:', error);
                showNotification('Network error. Please try again.', 'info');
            });
        }
        
        // Show decline reason selection
        function showDeclineReason() {
            const reasonSection = document.getElementById('declineReasonSection');
            const actionButtons = document.getElementById('actionButtons');
            
            if (reasonSection && actionButtons) {
                reasonSection.classList.remove('hidden');
                actionButtons.classList.add('hidden');
            }
        }
        
        // Hide decline reason selection
        function hideDeclineReason() {
            const reasonSection = document.getElementById('declineReasonSection');
            const actionButtons = document.getElementById('actionButtons');
            
            if (reasonSection && actionButtons) {
                reasonSection.classList.add('hidden');
                actionButtons.classList.remove('hidden');
            }
        }
        
        // Select decline reason and process decline
        function selectDeclineReason(reason, requestId, teacherId, studentName) {
            // Stop the notification sound immediately when user interacts
            stopNotificationSound();
            
            // Get the current duration from wait time counter before stopping it
            const counterElement = document.getElementById('waitTimeCounter');
            let durationSeconds = 0;
            
            if (counterElement) {
                // Extract duration from the counter text (e.g., "5 minutes, 30 seconds")
                const timeText = counterElement.textContent;
                const minutesMatch = timeText.match(/(\d+)\s*minutes?/);
                const secondsMatch = timeText.match(/(\d+)\s*seconds?/);
                
                const minutes = minutesMatch ? parseInt(minutesMatch[1]) : 0;
                const seconds = secondsMatch ? parseInt(secondsMatch[1]) : 0;
                
                durationSeconds = (minutes * 60) + seconds;
            }
            
            // Stop the wait time counter
            stopWaitTimeCounter();
            
            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('action', 'decline');
            formData.append('teacher_id', teacherId);
            formData.append('decline_reason', reason);
            formData.append('duration_seconds', durationSeconds);
            
            fetch('respond-to-consultation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showEnhancedNotification(`❌ Declined consultation request from ${studentName}. Reason: ${reason}`, 'info');
                    
                    // Remove the processed request from queue and shown tracking
                    requestQueue = requestQueue.filter(req => req.request_id !== requestId);
                    shownRequestIds.delete(requestId);
                    
                    // Close current modal
                    closeConsultationModal();
                    
                    // Update pending count
                    const pendingCountElement = document.getElementById('pendingCount');
                    if (pendingCountElement) {
                        pendingCountElement.textContent = requestQueue.length;
                    }
                    
                    // Log the response for debugging
                    console.log('Consultation declined successfully with reason:', data);
                } else {
                    showEnhancedNotification(data.error || 'Failed to decline request', 'info');
                }
            })
            .catch(error => {
                console.error('Error declining consultation:', error);
                showNotification('Network error. Please try again.', 'info');
            });
        }
        
        // Decline specific request (legacy function - now handled by selectDeclineReason)
        function declineRequest(requestId, teacherId, studentName) {
            // Stop the notification sound immediately when user interacts
            stopNotificationSound();
            
            // Stop the wait time counter
            stopWaitTimeCounter();
            
            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('action', 'decline');
            formData.append('teacher_id', teacherId);
            
            fetch('respond-to-consultation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showEnhancedNotification(`❌ Declined consultation request from ${studentName}.`, 'info');
                    
                    // Remove the processed request from queue and shown tracking
                    requestQueue = requestQueue.filter(req => req.request_id !== requestId);
                    shownRequestIds.delete(requestId);
                    
                    // Close current modal
                    closeConsultationModal();
                    
                    // Update pending count
                    const pendingCountElement = document.getElementById('pendingCount');
                    if (pendingCountElement) {
                        pendingCountElement.textContent = requestQueue.length;
                    }
                    
                    // Log the response for debugging
                    console.log('Consultation declined successfully:', data);
                } else {
                    showEnhancedNotification(data.error || 'Failed to decline request', 'info');
                }
            })
            .catch(error => {
                console.error('Error declining consultation:', error);
                showNotification('Network error. Please try again.', 'info');
            });
        }
        
        // Enhanced user interaction tracking for remote access
        function enableAudioPlayback() {
            if (!userInteracted) {
                userInteracted = true;
                console.log('User interaction detected, audio playback enabled for remote access');
                
                // Try to unlock audio context immediately
                enableAudioForRemoteAccess();
                
                // Show confirmation that audio is now enabled
                showEnhancedNotification('🔊 Audio notifications enabled for remote access!', 'success');
            }
        }
        
        // Enhanced event listeners for remote access
        document.addEventListener('click', enableAudioPlayback);
        document.addEventListener('keydown', enableAudioPlayback);
        document.addEventListener('touchstart', enableAudioPlayback);
        document.addEventListener('mousedown', enableAudioPlayback);
        document.addEventListener('mousemove', enableAudioPlayback);
        document.addEventListener('scroll', enableAudioPlayback);
        
        // Auto-enable audio on page load for remote access
        window.addEventListener('load', function() {
            setTimeout(() => {
                if (!userInteracted) {
                    console.log('Auto-enabling audio for remote access...');
                    enableAudioForRemoteAccess();
                    
                    // Show instruction for remote users
                    showEnhancedNotification('🖱️ Click anywhere on the page to enable sound notifications for remote access', 'info');
                }
            }, 2000);
        });
        
        // Check for requests every 3 seconds (reduced from 1 second to prevent excessive polling)
        setInterval(checkForConsultationRequests, 3000);
        
        // Refresh teacher list every 30 seconds for automatic updates
        setInterval(() => refreshTeacherList(true), 30000);
        
        // Also check immediately when page loads
        console.log('Teacher screen loaded, checking for requests immediately...');
        try {
            checkForConsultationRequests();
        } catch (error) {
            console.error('Error checking for consultation requests:', error);
        }
        
        // Add test button for debugging
        addTestButton();
        


        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'Escape':
                    closeConsultationModal();
                    break;

                case 's':
                case 'S':
                    toggleStatusBtn.click();
                    break;
                case 'v':
                case 'V':
                    // Toggle standby mode with 'V' key (Video)
                    const standbyBtn = document.getElementById('standbyToggleBtn');
                    if (standbyBtn) {
                        standbyBtn.click();
                    }
                    break;
            }
        });

        // Keep session alive with AJAX ping every 10 minutes (less frequent)
        setInterval(() => {
            // Silent ping to keep session alive
            fetch('check-consultation-requests.php?dept=<?php echo urlencode($selected_department); ?>', {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
                .then(response => response.json())
                .then(data => {
                    // Session kept alive silently
                })
                .catch(error => {
                    // Silent error handling - no console logs or alerts
                });
        }, 600000); // 10 minutes instead of 5

        // Ensure loading state is hidden on page load
        window.addEventListener('load', function() {
            const loadingState = document.getElementById('loadingState');
            if (loadingState) {
                loadingState.style.display = 'none';
            }
        });

        // Keep page active to prevent browser alerts

        // Handle page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Page is visible again, check for requests immediately
                checkForConsultationRequests();
                
                // Restart sound if modal is open
                if (isModalOpen && notificationAudio) {
                    playNotificationSound();
                }
            } else {
                // Page is hidden, pause sound to save resources
                if (notificationAudio) {
                    notificationAudio.pause();
                }
            }
        });


























        // Refresh teacher list automatically
        function refreshTeacherList(silent = false) {
            const department = '<?php echo htmlspecialchars($selected_department); ?>';
            
            // Show loading indicator
            const teacherListContainer = document.querySelector('.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-3');
            if (teacherListContainer) {
                teacherListContainer.style.opacity = '0.6';
                teacherListContainer.style.pointerEvents = 'none';
            }
            
            const apiUrl = `get-teachers-for-department.php?dept=${encodeURIComponent(department)}`;
            
            // Fetch updated teacher data
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateTeacherListDisplay(data.teachers);
                        updateTeacherCounts(data.teachers);
                        
                        // Only show notification if not silent (manual refresh)
                        if (!silent) {
                            showEnhancedNotification('🔄 Teacher list updated automatically', 'info');
                        }
                        
                        // Update the last refresh time
                        const refreshTimeDisplay = document.getElementById('refreshTimeDisplay');
                        if (refreshTimeDisplay) {
                            refreshTimeDisplay.textContent = new Date().toLocaleTimeString('en-US', {
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit'
                            });
                        }
                    } else {
                        console.error('Failed to refresh teacher list:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing teacher list:', error);
                })
                .finally(() => {
                    // Restore normal display
                    if (teacherListContainer) {
                        teacherListContainer.style.opacity = '1';
                        teacherListContainer.style.pointerEvents = 'auto';
                    }
                });
        }

        // Update teacher list display with new data
        function updateTeacherListDisplay(teachers) {
            const teacherListContainer = document.querySelector('.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-3');
            if (!teacherListContainer) {
                return;
            }

            if (teachers.length === 0) {
                teacherListContainer.innerHTML = `
                    <div class="text-center py-8 col-span-full">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-clock text-gray-400 text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-700 mb-2">No Teachers Available</h4>
                        <p class="text-gray-500">There are no teachers with consultation hours at this time.</p>
                        <p class="text-sm text-gray-400 mt-2">Please check back during consultation hours.</p>
                    </div>
                `;
                return;
            }

            teacherListContainer.innerHTML = teachers.map(teacher => {
                const is_available = (teacher.availability_status === 'available' && teacher.scan_time);
                const card_bg_class = is_available ? 'bg-gradient-to-br from-green-50 to-green-100 border-green-300' : 'bg-gradient-to-br from-gray-50 to-gray-100 border-gray-300';
                const status_text = is_available ? 'Available' : 'Not Scanned';
                const status_color = is_available ? 'text-green-700' : 'text-gray-600';
                const status_icon = is_available ? 'fas fa-check-circle text-green-500' : 'fas fa-clock text-gray-500';
                const avatar_border = is_available ? 'border-green-500' : 'border-gray-400';

                return `
                    <div class="teacher-card ${card_bg_class} border-2">
                        <div class="flex items-center space-x-2 sm:space-x-3 mb-2">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-gray-200 to-gray-300 rounded-full flex items-center justify-center flex-shrink-0 border-2 ${avatar_border}">
                                ${teacher.image_url ? 
                                    `<img src="../${teacher.image_url}" alt="Teacher" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full object-cover">` :
                                    `<i class="fas fa-user text-gray-600 text-sm sm:text-base"></i>`
                                }
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="text-gray-800 font-semibold text-sm sm:text-base truncate">
                                    ${teacher.first_name} ${teacher.last_name}
                                </h4>
                                <p class="text-gray-600 text-xs sm:text-sm truncate">
                                    ${teacher.position}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Status Indicator -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="${status_icon} text-xs sm:text-sm"></i>
                                <span class="${status_color} text-xs sm:text-sm font-medium">${status_text}</span>
                            </div>
                            ${is_available && teacher.scan_time ? 
                                `<span class="text-xs text-green-600">${new Date(teacher.scan_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'})}</span>` : 
                                ''
                            }
                        </div>
                        
                        <!-- Consultation Hours -->
                        ${teacher.start_time && teacher.end_time ? `
                            <div class="mt-2 pt-2 border-t border-gray-200">
                                <div class="flex items-center text-xs text-gray-600">
                                    <i class="fas fa-clock mr-1"></i>
                                    <span>
                                        ${new Date('2000-01-01T' + teacher.start_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'})} - 
                                        ${new Date('2000-01-01T' + teacher.end_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'})}
                                        ${teacher.room ? ' | ' + teacher.room : ''}
                                    </span>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }

        // Update teacher counts in the status section
        function updateTeacherCounts(teachers) {
            let available_count = 0;
            let not_scanned_count = 0;
            
            teachers.forEach(teacher => {
                if (teacher.availability_status === 'available' && teacher.scan_time) {
                    available_count++;
                } else {
                    not_scanned_count++;
                }
            });

            // Update the count displays
            const availableElement = document.querySelector('.text-green-600');
            const notScannedElement = document.querySelector('.text-gray-600');
            
            if (availableElement) {
                const iconElement = availableElement.querySelector('i');
                availableElement.innerHTML = `${iconElement.outerHTML} ${available_count} available`;
            }
            
            if (notScannedElement) {
                const iconElement = notScannedElement.querySelector('i');
                notScannedElement.innerHTML = `${iconElement.outerHTML} ${not_scanned_count} not scanned`;
            }
        }



        // =====================================================
        // FULLSCREEN FUNCTIONALITY
        // =====================================================

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
        
        // Initialize fullscreen functionality
        document.addEventListener('DOMContentLoaded', function() {
            const fullscreenBtn = document.getElementById('fullscreenBtn');
            
            if (fullscreenBtn) {
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
                
                // Keyboard shortcut for fullscreen (F11)
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'F11') {
                        e.preventDefault();
                        if (isFullscreen()) {
                            exitFullscreen();
                        } else {
                            enterFullscreen();
                        }
                    }
                });
            }
        });

        // =====================================================
        // STANDBY MODE FUNCTIONALITY
        // =====================================================

        // Standby Mode Functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing Standby Mode for Teacher Screen');
            
            const standbyVideoContainer = document.getElementById('standbyVideoContainer');
            const standbyVideo = document.getElementById('standbyVideo');
            const standbyIndicator = document.getElementById('standbyIndicator');
            const standbyCountdown = document.getElementById('standbyCountdown');
            const countdownTimer = document.getElementById('countdownTimer');
            const standbyToggleBtn = document.getElementById('standbyToggleBtn');
            
            let standbyTimeout;
            let countdownInterval;
            let isStandbyActive = false;
            let isManualStandby = false;
            let lastActivityTime = Date.now();
            const STANDBY_DELAY = 30000; // 30 seconds of inactivity
            
            // Function to start standby mode
            function startStandbyMode(manual = false) {
                if (isStandbyActive) return;
                
                console.log('Starting standby mode', manual ? '(manual)' : '(automatic)');
                isStandbyActive = true;
                isManualStandby = manual;
                
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
                
                // Update button text
                if (standbyToggleBtn) {
                    standbyToggleBtn.innerHTML = '<i class="fas fa-desktop mr-1 sm:mr-2"></i>Exit Standby';
                    standbyToggleBtn.classList.remove('bg-purple-500', 'hover:bg-purple-600');
                    standbyToggleBtn.classList.add('bg-red-500', 'hover:bg-red-600');
                }
                
                // Stop notification sound if playing
                stopNotificationSound();
            }
            
            // Function to stop standby mode
            function stopStandbyMode() {
                if (!isStandbyActive) return;
                
                console.log('Stopping standby mode');
                isStandbyActive = false;
                isManualStandby = false;
                
                // Hide standby video
                standbyVideoContainer.classList.remove('active');
                standbyIndicator.classList.remove('active');
                
                // Pause video
                if (standbyVideo) {
                    standbyVideo.pause();
                }
                
                // Update button text
                if (standbyToggleBtn) {
                    standbyToggleBtn.innerHTML = '<i class="fas fa-video mr-1 sm:mr-2"></i>Standby Mode';
                    standbyToggleBtn.classList.remove('bg-red-500', 'hover:bg-red-600');
                    standbyToggleBtn.classList.add('bg-purple-500', 'hover:bg-purple-600');
                }
                
                // Reset activity timer if not manual
                if (!isManualStandby) {
                    resetActivityTimer();
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
                
                // Stop standby mode if active and not manual
                if (isStandbyActive && !isManualStandby) {
                    stopStandbyMode();
                }
                
                // Don't set new timeout if in manual standby mode
                if (isManualStandby) {
                    return;
                }
                
                // Set new timeout
                standbyTimeout = setTimeout(() => {
                    startStandbyMode(false);
                }, STANDBY_DELAY);
                
                // Start countdown 5 seconds before standby
                setTimeout(() => {
                    if (!isStandbyActive && !isManualStandby) {
                        startCountdown();
                    }
                }, STANDBY_DELAY - 5000);
            }
            
            // Function to start countdown
            function startCountdown() {
                if (isStandbyActive || isManualStandby) return;
                
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
            
            // Manual standby toggle
            if (standbyToggleBtn) {
                standbyToggleBtn.addEventListener('click', function() {
                    if (isStandbyActive) {
                        stopStandbyMode();
                    } else {
                        // Clear any pending automatic standby
                        if (standbyTimeout) {
                            clearTimeout(standbyTimeout);
                        }
                        if (countdownInterval) {
                            clearInterval(countdownInterval);
                        }
                        standbyCountdown.classList.remove('active');
                        
                        startStandbyMode(true);
                    }
                });
            }
            
            // Event listeners for user activity (only if not in manual standby)
            const activityEvents = ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'];
            activityEvents.forEach(event => {
                document.addEventListener(event, function() {
                    if (!isManualStandby) {
                        resetActivityTimer();
                    }
                });
            });
            
            // Special handling for consultation modal interactions
            document.addEventListener('click', function(e) {
                // If clicking on modal elements, reset activity timer
                if (e.target.closest('#consultationModal') || 
                    e.target.closest('.notification-panel') ||
                    e.target.closest('button')) {
                    if (!isManualStandby) {
                        resetActivityTimer();
                    }
                }
            });
            
            // Handle consultation requests - exit standby when new request comes
            // Override the showConsultationModal function to exit standby
            if (typeof showConsultationModal !== 'undefined') {
                const originalShowConsultationModal = showConsultationModal;
                showConsultationModal = function(request) {
                    // Exit standby when consultation request comes
                    if (isStandbyActive && !isManualStandby) {
                        stopStandbyMode();
                    }
                    return originalShowConsultationModal.call(this, request);
                };
            }
            
            // Initialize activity timer
            resetActivityTimer();
            
            console.log('Standby mode initialized for Teacher Screen');
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
    </script>
</body>
</html>
