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

        .teacher-check-in {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .teacher-check-in h4 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        .qr-input {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
            width: 50%;
            font-family: 'Inter', sans-serif;
        }

        .qr-input:focus {
            border-color: #fb923c;
            box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.1);
            outline: none;
            background-color: #fffbf5;
        }
        
        /* Enhanced focus indicator */
        .qr-input.enhanced-focus {
            border-color: #fb923c !important;
            box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.1) !important;
            background-color: #fffbf5 !important;
            animation: focusPulse 2s infinite;
        }
        
        @keyframes focusPulse {
            0%, 100% { 
                box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.1);
            }
            50% { 
                box-shadow: 0 0 0 5px rgba(251, 146, 60, 0.2);
            }
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
                    
                    <!-- Teacher QR Code Input -->
                    <div class="teacher-check-in">
                        <h4>Teacher Check-In</h4>
                        
                        <form id="qrScannerForm" class="mb-3">
                            <div class="mb-3">
                                <input type="text" id="qrScannerInput" name="qrCode" placeholder="Type or scan QR code here..." 
                                       class="qr-input"
                                       autocomplete="off" autofocus required>

                            </div>
                            
                            <!-- Hidden submit button for form submission -->
                            <button type="submit" class="hidden">Submit</button>
                        </form>
                        
                        <div id="teacherAvailabilityStatus" class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200 hidden">
                            <div class="flex items-center justify-center space-x-2">
                                <div id="availabilityIndicator" class="w-3 h-3 rounded-full"></div>
                                <span id="availabilityText" class="text-sm font-medium"></span>
                            </div>
                        </div>
                    </div>
                        
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
                <button id="refreshBtn" class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg transition-all duration-300 mobile-btn shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-sync-alt mr-1 sm:mr-2"></i>
                    Refresh
                </button>
            </div>

            <!-- Session Info -->
            <div class="mt-6 sm:mt-8 text-xs sm:text-sm text-gray-600 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg p-3 border border-gray-200 shadow-sm">
                <p class="mb-1">Session ID: <span class="font-mono text-xs text-gray-700"><?php echo $office_session_id; ?></span></p>
                <p>Last updated: <span id="lastUpdated" class="text-gray-700"><?php echo date('H:i:s'); ?></span></p>
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
        
        // Play notification sound continuously with enhanced remote access support
        function playNotificationSound() {
            try {
                // Check if sound is disabled
                if (window.soundEnabled === false) {
                    console.log('Sound is disabled, skipping audio notification');
                    return;
                }
                
                // Stop any existing sound
                stopNotificationSound();
                
                // Enhanced user interaction check for remote access
                if (!userInteracted) {
                    console.log('User has not interacted with page yet, attempting to enable audio...');
                    // Try to enable audio with user interaction simulation
                    enableAudioForRemoteAccess();
                    // Use fallback notification method
                    showEnhancedNotification('🔔 New consultation request received! Click anywhere to enable sound.', 'success');
                    return;
                }
                
                // Try multiple audio sources for better compatibility
                const audioSources = [
                    'notification-sound.mp3',
                    'audio/notification-sound.mp3',
                    './notification-sound.mp3'
                ];
                
                let audioLoaded = false;
                
                for (let source of audioSources) {
                    try {
                        // Create new audio instance
                        notificationAudio = new Audio(source);
                        notificationAudio.volume = 0.7; // Increased volume for remote access
                        notificationAudio.crossOrigin = 'anonymous'; // Handle CORS issues
                        
                        // Add error handling for audio loading
                        notificationAudio.addEventListener('error', (e) => {
                            console.log(`Audio loading error for ${source}:`, e);
                        });
                        
                        notificationAudio.addEventListener('canplaythrough', () => {
                            console.log(`Audio loaded successfully: ${source}`);
                            audioLoaded = true;
                        });
                        
                        // Try to play sound immediately
                        const playPromise = notificationAudio.play();
                        
                        if (playPromise !== undefined) {
                            playPromise.then(() => {
                                console.log(`Audio playback started successfully: ${source}`);
                                audioLoaded = true;
                                
                                // Set up continuous playback every 3 seconds
                                soundInterval = setInterval(() => {
                                    if (notificationAudio && window.soundEnabled !== false) {
                                        notificationAudio.currentTime = 0; // Reset to beginning
                                        notificationAudio.play().catch(e => {
                                            console.log('Continuous audio playback failed:', e);
                                            // Try fallback sound
                                            playWebAudioFallback();
                                        });
                                    }
                                }, 3000); // Repeat every 3 seconds
                                
                                // Set maximum duration (5 minutes) to prevent infinite sound
                                setTimeout(() => {
                                    if (soundInterval) {
                                        console.log('Maximum sound duration reached, stopping automatically');
                                        stopNotificationSound();
                                    }
                                }, 300000); // 5 minutes
                                
                            }).catch(e => {
                                console.log(`Audio playback failed for ${source}:`, e);
                                if (source === audioSources[audioSources.length - 1]) {
                                    // Last source failed, use fallback
                                    playWebAudioFallback();
                                }
                            });
                        }
                        
                        // If we successfully created audio, break the loop
                        if (notificationAudio) {
                            break;
                        }
                        
                    } catch (e) {
                        console.log(`Failed to create audio for ${source}:`, e);
                        continue;
                    }
                }
                
                // If no audio sources worked, use Web Audio API fallback
                if (!audioLoaded) {
                    console.log('All audio sources failed, using Web Audio API fallback');
                    playWebAudioFallback();
                }
                
                console.log('Notification sound system initialized');
            } catch (e) {
                console.log('Audio notification not supported:', e);
                // Ultimate fallback
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
                        
                        // Play notification sound using improved system
                        playNotificationSound();
                        
                        // Show notification
                        showEnhancedNotification(`📋 ${data.total_requests} consultation request(s) received!`, 'success');
                    } else {
                        // No pending requests
                        const pendingCountElement = document.getElementById('pendingCount');
                        if (pendingCountElement) {
                            pendingCountElement.textContent = '0';
                        }
                        
                        // Clear queue and close modal
                        requestQueue = [];
                        closeConsultationModal();
                    }
                })
                .catch(error => {
                    console.error('Error checking for consultation requests:', error);
                    // Don't show alerts for AJAX errors
                });
        }

        // Update request queue and show next request if modal is closed
        function updateRequestQueue(newRequests) {
            // Add new requests to queue (avoid duplicates)
            newRequests.forEach(newRequest => {
                const exists = requestQueue.find(req => req.request_id === newRequest.request_id);
                if (!exists) {
                    requestQueue.push(newRequest);
                }
            });
            
            // Remove completed requests from queue
            requestQueue = requestQueue.filter(req => 
                newRequests.find(newReq => newReq.request_id === req.request_id)
            );
            
            console.log('Request queue updated:', requestQueue.length, 'requests');
            
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
                <!-- Enhanced backdrop with stronger blur and focus -->
                <div class="fixed inset-0 bg-black bg-opacity-80 backdrop-blur-lg animate-pulse flex items-center justify-center p-4"></div>
                
                <!-- Enhanced modal with better focus and animations -->
                <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl shadow-2xl border-4 border-orange-500 p-4 sm:p-8 max-w-4xl w-11/12 relative transform transition-all duration-500 modal-focus">
                    
                    <!-- Enhanced header with attention-grabbing design -->
                    <div class="flex items-center justify-between mb-4 sm:mb-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full flex items-center justify-center mr-3 sm:mr-4 animate-pulse shadow-lg">
                                <i class="fas fa-bell text-white text-lg sm:text-2xl animate-bounce"></i>
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-2xl font-bold text-gray-800 bg-gradient-to-r from-orange-600 to-orange-700 bg-clip-text text-transparent">
                                    🎯 New Consultation Request
                                </h3>
                                <p class="text-xs sm:text-sm text-gray-500 mt-1">${queueInfo}</p>
                                <p class="text-xs text-orange-600 mt-1 animate-pulse">
                                    <i class="fas fa-volume-up mr-1"></i>Sound will continue until you respond
                                </p>
                            </div>
                        </div>
                        <button onclick="closeConsultationModal()" class="text-gray-500 hover:text-gray-700 text-xl sm:text-2xl transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Enhanced request details with better visual hierarchy -->
                    <div class="space-y-4 sm:space-y-6 mb-6 sm:mb-8">
                        <div class="bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl p-6 sm:p-8 border-l-4 border-orange-500 shadow-lg">
                            <div class="flex items-center mb-4 sm:mb-6">
                                <i class="fas fa-user-graduate text-orange-600 mr-3 sm:mr-4 text-xl sm:text-2xl"></i>
                                <span class="font-bold text-orange-800 text-lg sm:text-2xl">Student Information</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 text-sm sm:text-base">
                                <div class="flex justify-between items-center bg-white bg-opacity-50 rounded-lg p-3 sm:p-4">
                                    <span class="text-orange-700 font-medium text-base sm:text-lg">👤 Name:</span>
                                    <span class="font-bold text-orange-900 text-lg sm:text-xl">${request.student_name}</span>
                                </div>

                                <div class="flex justify-between items-center bg-white bg-opacity-50 rounded-lg p-3 sm:p-4">
                                    <span class="text-orange-700 font-medium text-base sm:text-lg">🆔 Student ID:</span>
                                    <span class="font-bold text-orange-900 text-base sm:text-lg">${request.student_id || 'Not provided'}</span>
                                </div>

                                <div class="flex justify-between items-center bg-white bg-opacity-50 rounded-lg p-3 sm:p-4">
                                    <span class="text-orange-700 font-medium text-base sm:text-lg">🏫 Department:</span>
                                    <span class="font-bold text-orange-900 text-base sm:text-lg">${request.student_dept || 'Not specified'}</span>
                                </div>

                                <div class="flex justify-between items-center bg-white bg-opacity-50 rounded-lg p-3 sm:p-4">
                                    <span class="text-orange-700 font-medium text-base sm:text-lg">👨‍🏫 Requested:</span>
                                    <span class="font-bold text-orange-900 text-base sm:text-lg">${request.teacher_name}</span>
                                </div>
                                <div class="flex justify-between items-center bg-white bg-opacity-50 rounded-lg p-3 sm:p-4">
                                    <span class="text-orange-700 font-medium text-base sm:text-lg">🕐 Time:</span>
                                    <span class="font-bold text-orange-900 text-base sm:text-lg">${requestTime}</span>
                                </div>
                                <div class="flex justify-between items-center bg-white bg-opacity-50 rounded-lg p-3 sm:p-4 md:col-span-2">
                                    <span class="text-orange-700 font-medium text-base sm:text-lg">⏱️ Wait Time:</span>
                                    <span id="waitTimeCounter" class="font-bold text-orange-900 text-base sm:text-lg" data-start-time="${request.request_time}">${request.minutes_ago} minutes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Decline Reason Selection (Hidden by default) -->
                    <div id="declineReasonSection" class="hidden mb-6 sm:mb-8">
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 sm:p-6">
                            <h4 class="text-lg sm:text-xl font-bold text-red-800 mb-3 sm:mb-4">
                                <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>
                                Please select a reason for declining:
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                                <button onclick="selectDeclineReason('Busy with other consultation', '${request.request_id}', '${request.teacher_id}', '${request.student_name}')" 
                                        class="reason-pill bg-white border-2 border-red-300 hover:border-red-500 hover:bg-red-50 text-red-700 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105">
                                    <i class="fas fa-users mr-1"></i> Busy with other consultation
                                </button>
                                <button onclick="selectDeclineReason('In a meeting', '${request.request_id}', '${request.teacher_id}', '${request.student_name}')" 
                                        class="reason-pill bg-white border-2 border-red-300 hover:border-red-500 hover:bg-red-50 text-red-700 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105">
                                    <i class="fas fa-handshake mr-1"></i> In a meeting
                                </button>
                                <button onclick="selectDeclineReason('Office hours ended', '${request.request_id}', '${request.teacher_id}', '${request.student_name}')" 
                                        class="reason-pill bg-white border-2 border-red-300 hover:border-red-500 hover:bg-red-50 text-red-700 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105">
                                    <i class="fas fa-clock mr-1"></i> Office hours ended
                                </button>
                                <button onclick="selectDeclineReason('Not available at this time', '${request.request_id}', '${request.teacher_id}', '${request.student_name}')" 
                                        class="reason-pill bg-white border-2 border-red-300 hover:border-red-500 hover:bg-red-50 text-red-700 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105">
                                    <i class="fas fa-calendar-times mr-1"></i> Not available at this time
                                </button>
                                <button onclick="selectDeclineReason('Technical issues', '${request.request_id}', '${request.teacher_id}', '${request.student_name}')" 
                                        class="reason-pill bg-white border-2 border-red-300 hover:border-red-500 hover:bg-red-50 text-red-700 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Technical issues
                                </button>
                                <button onclick="selectDeclineReason('Other', '${request.request_id}', '${request.teacher_id}', '${request.student_name}')" 
                                        class="reason-pill bg-white border-2 border-red-300 hover:border-red-500 hover:bg-red-50 text-red-700 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105">
                                    <i class="fas fa-ellipsis-h mr-1"></i> Other
                                </button>
                            </div>
                            <div class="mt-3 sm:mt-4 text-center">
                                <button onclick="hideDeclineReason()" 
                                        class="text-gray-600 hover:text-gray-800 text-sm font-medium transition-colors">
                                    <i class="fas fa-arrow-left mr-1"></i> Back to actions
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced action buttons with better focus -->
                    <div id="actionButtons" class="flex flex-col lg:flex-row space-y-4 sm:space-y-0 lg:space-x-6 mb-6 sm:mb-8">
                                                    <button onclick="acceptRequest('${request.request_id}', '${request.teacher_id}', '${request.student_name}')" 
                                    class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white px-6 sm:px-8 py-4 sm:py-6 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-xl font-bold text-lg sm:text-xl border-2 border-orange-400 hover:border-orange-300 mobile-btn">
                            <i class="fas fa-check mr-3 sm:mr-4 text-xl sm:text-2xl"></i> Accept Request
                        </button>
                        <button onclick="showDeclineReason()" 
                                class="flex-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 sm:px-8 py-4 sm:py-6 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-xl font-bold text-lg sm:text-xl border-2 border-red-400 hover:border-red-300 mobile-btn">
                            <i class="fas fa-times mr-3 sm:mr-4 text-xl sm:text-2xl"></i> Decline Request
                        </button>
                    </div>
                    
                    <!-- Enhanced queue info with better styling -->
                    ${requestQueue.length > 1 ? `
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 sm:p-4 text-center">
                            <p class="text-xs sm:text-sm text-orange-800 font-medium">
                                <i class="fas fa-clock mr-1 sm:mr-2 text-orange-600"></i>
                                ⏳ ${requestQueue.length - 1} more request(s) waiting in queue
                            </p>
                        </div>
                    ` : ''}
                    
                    <!-- Keyboard shortcuts info -->
                    <div class="mt-3 sm:mt-4 text-center">
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-keyboard mr-1"></i>
                            Press <kbd class="bg-gray-200 px-1 sm:px-2 py-1 rounded text-xs">A</kbd> to Accept, 
                            <kbd class="bg-gray-200 px-1 sm:px-2 py-1 rounded text-xs">D</kbd> to Decline, 
                            <kbd class="bg-gray-200 px-1 sm:px-2 py-1 rounded text-xs">ESC</kbd> to Close
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
        
        // Close consultation modal
        function closeConsultationModal() {
            // Stop the notification sound when modal is closed
            stopNotificationSound();
            
            // Stop the wait time counter
            stopWaitTimeCounter();
            
            const modal = document.getElementById('consultationModal');
            if (modal) {
                modal.remove();
            }
            isModalOpen = false;
            
            // Show next request if available
            setTimeout(() => {
                showNextRequest();
            }, 500);
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
                    
                    // Remove the processed request from queue
                    requestQueue = requestQueue.filter(req => req.request_id !== requestId);
                    
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
                    
                    // Remove the processed request from queue
                    requestQueue = requestQueue.filter(req => req.request_id !== requestId);
                    
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
                    
                    // Remove the processed request from queue
                    requestQueue = requestQueue.filter(req => req.request_id !== requestId);
                    
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
        
        // Check for requests every 1 second
        setInterval(checkForConsultationRequests, 1000);
        
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
                case 'a':
                case 'A':
                    if (isModalOpen && requestQueue.length > 0) {
                        const currentRequest = requestQueue[0];
                        acceptRequest(currentRequest.request_id, currentRequest.teacher_id, currentRequest.student_name);
                    }
                    break;
                case 'd':
                case 'D':
                    if (isModalOpen && requestQueue.length > 0) {
                        const currentRequest = requestQueue[0];
                        declineRequest(currentRequest.request_id, currentRequest.teacher_id, currentRequest.student_name);
                    }
                    break;
                case 's':
                case 'S':
                    toggleStatusBtn.click();
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

        // =====================================================
        // TEACHER QR CODE SCANNER FUNCTIONALITY
        // =====================================================

        // QR Scanner variables removed - using form-only approach
        let currentTeacherId = null;

        // Enhanced QR input focus functionality
        function ensureQRInputFocus() {
            const qrScannerInput = document.getElementById('qrScannerInput');
            if (qrScannerInput) {
                // Multiple focus attempts with different timing
                qrScannerInput.focus();
                
                // Add enhanced focus class for visual feedback
                qrScannerInput.classList.add('enhanced-focus');
                
                // Remove enhanced focus class after a delay
                setTimeout(() => {
                    qrScannerInput.classList.remove('enhanced-focus');
                }, 3000);
                
                // Delayed focus attempts
                setTimeout(() => {
                    qrScannerInput.focus();
                    qrScannerInput.click(); // Sometimes click helps with focus
                }, 100);
                
                setTimeout(() => {
                    qrScannerInput.focus();
                }, 300);
                
                setTimeout(() => {
                    qrScannerInput.focus();
                }, 500);
                
                // Scroll input into view if needed
                qrScannerInput.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                
                console.log('QR input field focused with enhanced method');
                return true;
            }
            console.warn('QR input field not found for focusing');
            return false;
        }

        // Initialize QR code form functionality
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const qrScannerForm = document.getElementById('qrScannerForm');
                const qrScannerInput = document.getElementById('qrScannerInput');
            
            if (qrScannerForm && qrScannerInput) {
                
                // Handle form submission
                qrScannerForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const inputValue = qrScannerInput.value.trim();
                    
                    if (inputValue) {
                        verifyTeacherInDatabase(inputValue);
                    } else {
                        showEnhancedNotification('❌ Please enter a QR code.', 'error');
                    }
                });
                
                // Enhanced focus on the input field
                ensureQRInputFocus();
                
                // Add focus event listeners to maintain focus
                qrScannerInput.addEventListener('blur', function() {
                    // Re-focus after a short delay if no other element is being focused
                    setTimeout(() => {
                        if (document.activeElement === document.body || document.activeElement === null) {
                            ensureQRInputFocus();
                        }
                    }, 100);
                });
                
                // Ensure focus on window focus
                window.addEventListener('focus', function() {
                    setTimeout(() => {
                        ensureQRInputFocus();
                    }, 100);
                });
                
                // Add click listener to page to refocus QR input (helpful for touch devices)
                document.addEventListener('click', function(e) {
                    // Only refocus if clicking on non-interactive elements
                    if (!e.target.matches('button, a, input, select, textarea, [tabindex]')) {
                        setTimeout(() => {
                            ensureQRInputFocus();
                        }, 50);
                    }
                });
                
                // Add keyboard shortcut to focus QR input (F2 key)
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'F2') {
                        e.preventDefault();
                        ensureQRInputFocus();
                    }
                });
                
            } else {
                console.error('QR form or input field not found!');
            }
            } catch (error) {
                console.error('Error in QR Scanner initialization:', error);
            }
        });

        // Fallback initialization in case DOMContentLoaded doesn't work
        setTimeout(function() {
            console.log('=== FALLBACK QR SCANNER INITIALIZATION ===');
            const qrScannerForm = document.getElementById('qrScannerForm');
            const qrScannerInput = document.getElementById('qrScannerInput');
            
            if (qrScannerForm && qrScannerInput) {
                console.log('Fallback: QR Scanner elements found');
                // Re-initialize if not already done
                if (!qrScannerInput.hasAttribute('data-initialized')) {
                    console.log('Fallback: Initializing QR Scanner');
                    qrScannerInput.setAttribute('data-initialized', 'true');
                    
                    // Add event listeners
                    qrScannerForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        console.log('Fallback: Form submitted');
                        const inputValue = qrScannerInput.value.trim();
                        if (inputValue) {
                            verifyTeacherInDatabase(inputValue);
                        }
                    });
                    
                    qrScannerInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            console.log('Fallback: Enter pressed');
                            const inputValue = this.value.trim();
                            if (inputValue) {
                                verifyTeacherInDatabase(inputValue);
                            }
                        }
                    });
                    
                    // Use enhanced focus function
                    ensureQRInputFocus();
                }
            } else {
                console.log('Fallback: QR Scanner elements not found');
            }
        }, 1000);
        
        // Additional focus attempts at different intervals
        setTimeout(() => {
            console.log('=== ADDITIONAL FOCUS ATTEMPT (2s) ===');
            ensureQRInputFocus();
        }, 2000);
        
        setTimeout(() => {
            console.log('=== ADDITIONAL FOCUS ATTEMPT (3s) ===');
            ensureQRInputFocus();
        }, 3000);

        // Test and debug functions removed - simplified form-only approach

        // Verify teacher exists in database before showing confirmation
        // Global flag to prevent multiple simultaneous verifications
        let isVerifying = false;
        
        function verifyTeacherInDatabase(inputValue) {
            // Prevent multiple simultaneous calls
            if (isVerifying) {
                console.log('Verification already in progress, skipping...');
                return;
            }
            
            isVerifying = true;
            console.log('Verifying teacher in database:', inputValue);
            
            // Show loading indicator
            const qrInput = document.getElementById('qrScannerInput');
            if (qrInput) {
                qrInput.disabled = true;
                qrInput.placeholder = 'Verifying QR code...';
            }
            
            // Show loading notification
            showEnhancedNotification('🔍 Verifying QR code...', 'info');
            
            // Determine if input is numeric (teacher ID) or alphanumeric (QR code)
            const isNumeric = /^\d+$/.test(inputValue);
            const apiUrl = isNumeric 
                ? '../api/teacher-availability-handler.php?action=verify_teacher&teacher_id=' + inputValue
                : '../api/teacher-availability-handler.php?action=verify_teacher&qr_code=' + encodeURIComponent(inputValue);
            
            console.log('API URL:', apiUrl);
            
            // Make AJAX call to verify teacher
            fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-cache'
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Verification response:', data);
                
                // Re-enable input field and reset verification flag
                if (qrInput) {
                    qrInput.disabled = false;
                    qrInput.placeholder = 'Scan QR code here...';
                }
                isVerifying = false;
                
                if (data.success) {
                    console.log('Teacher verified successfully:', data.teacher.name);
                    showEnhancedNotification('✅ Teacher verified: ' + data.teacher.name, 'success');
                    showTeacherAvailabilityConfirmation(data.teacher.id, data.teacher);
                } else {
                    console.log('Teacher verification failed:', data.error);
                    showEnhancedNotification('❌ ' + (data.error || 'Teacher not found in database'), 'error');
                    
                    // Clear the input field for next scan
                    if (qrInput) {
                        qrInput.value = '';
                        ensureQRInputFocus();
                    }
                }
            })
            .catch(error => {
                console.error('Error verifying teacher:', error);
                
                // Re-enable input field and reset verification flag
                if (qrInput) {
                    qrInput.disabled = false;
                    qrInput.placeholder = 'Scan QR code here...';
                }
                isVerifying = false;
                
                showEnhancedNotification('❌ Network error. Please try again.', 'error');
                
                // Clear the input field for next scan
                if (qrInput) {
                    qrInput.value = '';
                    qrInput.focus();
                }
            });
        }

        // Show teacher availability confirmation dialog
        function showTeacherAvailabilityConfirmation(teacherId, teacherData = null) {
            console.log('Showing confirmation dialog for teacher:', teacherId);
            
            // Remove any existing dialog first
            const existingDialog = document.getElementById('availabilityConfirmationDialog');
            if (existingDialog) {
                existingDialog.remove();
            }
            
            // Create confirmation dialog with enhanced styling
            const dialog = document.createElement('div');
            dialog.id = 'availabilityConfirmationDialog';
            dialog.className = 'fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4';
            dialog.innerHTML = `
                <div class="bg-gradient-to-br from-white to-gray-50 rounded-3xl shadow-2xl border-4 border-orange-400 p-8 max-w-2xl w-full transform scale-95 opacity-0 transition-all duration-500 ease-out">
                    
                    <!-- Header with animated icon -->
                    <div class="text-center mb-8">
                        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 shadow-lg mb-6 animate-pulse">
                            <i class="fas fa-user-check text-white text-3xl"></i>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-800 mb-2 bg-gradient-to-r from-orange-600 to-orange-700 bg-clip-text text-transparent">
                            Confirm Availability
                        </h3>
                        <div class="w-24 h-1 bg-gradient-to-r from-orange-500 to-orange-600 rounded-full mx-auto"></div>
                    </div>
                    
                    <!-- Teacher Information Card -->
                    ${teacherData ? `
                    <div class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-2xl p-6 mb-8 border-l-4 border-orange-500 shadow-lg">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-user-graduate text-orange-600 mr-3 text-2xl"></i>
                            <span class="text-xl font-bold text-orange-800">Teacher Information</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white bg-opacity-70 rounded-xl p-4 border border-orange-200">
                                <span class="text-orange-700 font-medium text-lg">👤 Name:</span>
                                <span class="font-bold text-orange-900 text-xl block mt-1">${teacherData.name || 'N/A'}</span>
                            </div>
                            <div class="bg-white bg-opacity-70 rounded-xl p-4 border border-orange-200">
                                <span class="text-orange-700 font-medium text-lg">🏫 Department:</span>
                                <span class="font-bold text-orange-900 text-xl block mt-1">${teacherData.department || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Confirmation Message -->
                    <div class="text-center mb-8">
                        <p class="text-xl text-gray-700 leading-relaxed">
                            Are you ready to mark yourself as <span class="font-bold text-orange-600">available</span> for student consultations?
                        </p>
                        <p class="text-lg text-gray-600 mt-3">
                            Students will be able to see you as an available teacher for consultation requests.
                        </p>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
                        <button onclick="cancelAvailabilityConfirmation()" 
                                class="flex-1 bg-gradient-to-r from-gray-400 to-gray-500 hover:from-gray-500 hover:to-gray-600 text-white px-8 py-4 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl font-bold text-lg border-2 border-gray-300 hover:border-gray-400">
                            <i class="fas fa-times mr-3 text-xl"></i>
                            Cancel
                        </button>
                        <button onclick="confirmAvailabilityNow('${teacherId}')" 
                                class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white px-8 py-4 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-xl hover:shadow-2xl font-bold text-lg border-2 border-orange-400 hover:border-orange-300">
                            <i class="fas fa-check mr-3 text-xl"></i>
                            Yes, Mark Available
                        </button>
                    </div>
                    
                    <!-- Footer Note -->
                    <div class="text-center mt-6">
                        <p class="text-sm text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            You can change your availability status anytime
                        </p>
                    </div>
                </div>
            `;
            
            document.body.appendChild(dialog);
            
            // Add popup animation
            setTimeout(() => {
                const dialogContent = dialog.querySelector('.bg-gradient-to-br');
                if (dialogContent) {
                    dialogContent.classList.remove('scale-95', 'opacity-0');
                    dialogContent.classList.add('scale-100', 'opacity-100');
                }
            }, 100);
            
            console.log('Enhanced confirmation dialog created and displayed');
        }

        // Cancel availability confirmation
        function cancelAvailabilityConfirmation() {
            const dialog = document.getElementById('availabilityConfirmationDialog');
            if (dialog) {
                const dialogContent = dialog.querySelector('.bg-gradient-to-br');
                if (dialogContent) {
                    // Add closing animation
                    dialogContent.classList.remove('scale-100', 'opacity-100');
                    dialogContent.classList.add('scale-95', 'opacity-0');
                    
                    // Remove dialog after animation
                    setTimeout(() => {
                        dialog.remove();
                    }, 300);
                } else {
                    dialog.remove();
                }
            }
            
            // Clear the QR scanner input field and refocus
            const qrInput = document.getElementById('qrScannerInput');
            if (qrInput) {
                qrInput.value = '';
                ensureQRInputFocus();
            }
        }

        // Confirm availability immediately
        function confirmAvailabilityNow(teacherId) {
            // Remove the confirmation dialog
            const dialog = document.getElementById('availabilityConfirmationDialog');
            if (dialog) {
                dialog.remove();
            }
            
            // Mark teacher as available
            markTeacherAvailable(teacherId);
            
            // Clear the input field and refocus for next scan
            const qrInput = document.getElementById('qrScannerInput');
            if (qrInput) {
                qrInput.value = '';
                ensureQRInputFocus();
            }
        }

        // Camera-based QR scanner functions removed - using form-only approach

        // Mark teacher as available
        function markTeacherAvailable(teacherId) {
            const formData = new FormData();
            formData.append('teacher_id', teacherId);
            formData.append('notes', 'QR code scan confirmation');
            
            fetch('../api/teacher-availability-handler.php?action=mark_available', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentTeacherId = teacherId;
                    showEnhancedNotification('✅ Teacher availability confirmed successfully!', 'success');
                    updateTeacherAvailabilityStatus(true, data.teacher);
                    
                    // Automatically refresh the teacher list to show updated status
                    setTimeout(() => {
                        refreshTeacherList();
                    }, 1000); // Small delay to ensure database update is complete
                } else {
                    showEnhancedNotification(data.error || 'Failed to confirm availability', 'error');
                }
            })
            .catch(error => {
                console.error('Error marking teacher available:', error);
                showEnhancedNotification('Network error. Please try again.', 'error');
            });
        }

        // Mark teacher as unavailable
        function markTeacherUnavailable() {
            if (!currentTeacherId) {
                showEnhancedNotification('No teacher ID found. Please scan your ID first.', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('teacher_id', currentTeacherId);
            formData.append('notes', 'Manually marked unavailable');
            
            fetch('../api/teacher-availability-handler.php?action=mark_unavailable', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showEnhancedNotification('❌ Teacher marked as unavailable', 'info');
                    updateTeacherAvailabilityStatus(false);
                    currentTeacherId = null;
                    
                    // Automatically refresh the teacher list to show updated status
                    setTimeout(() => {
                        refreshTeacherList();
                    }, 1000); // Small delay to ensure database update is complete
                } else {
                    showEnhancedNotification(data.error || 'Failed to mark as unavailable', 'error');
                }
            })
            .catch(error => {
                console.error('Error marking teacher unavailable:', error);
                showEnhancedNotification('Network error. Please try again.', 'error');
            });
        }

        // Update teacher availability status display
        function updateTeacherAvailabilityStatus(isAvailable, teacherData = null) {
            const statusDiv = document.getElementById('teacherAvailabilityStatus');
            const indicator = document.getElementById('availabilityIndicator');
            const text = document.getElementById('availabilityText');
            
            if (statusDiv && indicator && text) {
                statusDiv.classList.remove('hidden');
                
                if (isAvailable && teacherData) {
                    indicator.className = 'w-3 h-3 rounded-full bg-orange-500';
                    text.textContent = `Available: ${teacherData.first_name} ${teacherData.last_name} (${teacherData.department})`;
                    text.className = 'text-sm font-medium text-orange-700';
                } else {
                    indicator.className = 'w-3 h-3 rounded-full bg-red-500';
                    text.textContent = 'Not Available';
                    text.className = 'text-sm font-medium text-red-700';
                }
            }
        }

        // Check teacher availability status on page load
        function checkTeacherAvailabilityStatus() {
            if (currentTeacherId) {
                fetch(`../api/teacher-availability-handler.php?action=get_status&teacher_id=${currentTeacherId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.availability) {
                        updateTeacherAvailabilityStatus(true, {
                            first_name: data.availability.first_name,
                            last_name: data.availability.last_name,
                            department: data.availability.department
                        });
                    }
                })
                .catch(error => {
                    console.error('Error checking teacher availability status:', error);
                });
            }
        }

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

        // Make functions globally available
        // Camera-based QR scanner window functions removed
        window.markTeacherUnavailable = markTeacherUnavailable;
        window.cancelAvailabilityConfirmation = cancelAvailabilityConfirmation;
        window.confirmAvailabilityNow = confirmAvailabilityNow;

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
