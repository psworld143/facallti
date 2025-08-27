<?php
session_start();
require_once '../config/database.php';

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

// Get teachers available for consultation in the selected department
$teachers_query = "SELECT DISTINCT 
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
                   WHERE f.is_active = 1 
                   AND f.department = ?
                   ORDER BY f.first_name, f.last_name";

$teachers_stmt = mysqli_prepare($conn, $teachers_query);
if ($teachers_stmt) {
    mysqli_stmt_bind_param($teachers_stmt, "s", $selected_department);
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
    
    // Try partial matching
    $partial_query = "SELECT DISTINCT 
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
                       WHERE f.is_active = 1 
                       AND f.department LIKE ?
                       ORDER BY f.first_name, f.last_name";
    
    $partial_stmt = mysqli_prepare($conn, $partial_query);
    if ($partial_stmt) {
        $search_term = '%' . $selected_department . '%';
        mysqli_stmt_bind_param($partial_stmt, "s", $search_term);
        mysqli_stmt_execute($partial_stmt);
        $partial_result = mysqli_stmt_get_result($partial_stmt);
        
        while ($row = mysqli_fetch_assoc($partial_result)) {
            $teachers[] = $row;
        }
    }
}

// If still no teachers found, get all available teachers
if (empty($teachers)) {
    
    $fallback_query = "SELECT DISTINCT 
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
                       WHERE f.is_active = 1 
                       ORDER BY f.department, f.first_name, f.last_name";
    
    $fallback_result = mysqli_query($conn, $fallback_query);
    while ($row = mysqli_fetch_assoc($fallback_result)) {
        $teachers[] = $row;
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
    <style>
        /* Enhanced Teacher Card Styling */
        .teacher-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
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

        .teacher-card:hover::before {
            left: 100%;
        }

        .teacher-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15), 0 8px 16px -4px rgba(0, 0, 0, 0.1);
            border-color: #FF6B35;
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
            max-width: 500px;
            width: 100%;
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
    <!-- Header -->
    <header class="header-gradient shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center py-4 sm:py-6">
                <div class="flex items-center mb-4 sm:mb-0">
                    <div class="relative">
                        <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-10 sm:h-14 w-auto float-animation">
                        <div class="absolute -top-1 -right-1 w-3 sm:w-4 h-3 sm:h-4 bg-yellow-400 rounded-full glow-animation"></div>
                    </div>
                    <div class="ml-4 sm:ml-6">
                        <h1 class="text-lg sm:text-2xl font-bold text-white mb-1">Student Consultation Portal</h1>
                        <p class="text-xs sm:text-sm text-orange-100">
                            <?php if ($selected_department): ?>
                                <i class="fas fa-building mr-1 sm:mr-2"></i><?php echo htmlspecialchars($selected_department); ?>
                            <?php else: ?>
                                <i class="fas fa-users mr-1 sm:mr-2"></i>Available Teachers
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 mobile-nav">
                    <a href="index.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-3 sm:px-4 py-2 rounded-lg transition-all duration-300 hover:scale-105 mobile-back-btn text-sm sm:text-base">
                        <i class="fas fa-arrow-left mr-1 sm:mr-2"></i>Back
                    </a>
                </div>
            </div>
        </div>
    </header>

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
        
        <!-- Page Header Card -->
        <div class="enhanced-card rounded-xl shadow-lg p-4 sm:p-8 mb-6 sm:mb-8 border-l-4 border-orange-500">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between space-y-4 lg:space-y-0">
                <div class="flex items-center">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full flex items-center justify-center mr-4 sm:mr-6 float-animation">
                        <i class="fas fa-chalkboard-teacher text-white text-lg sm:text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-3xl font-bold text-gray-800 mb-2">Available Teachers</h2>
                        <p class="text-gray-600 text-sm sm:text-lg">Tap on a teacher to automatically notify them and start consultation</p>
                        <div class="flex flex-col sm:flex-row items-start sm:items-center mt-3 space-y-2 sm:space-y-0 sm:space-x-4">
                        </div>
                    </div>
                </div>
                <div class="text-center lg:text-right w-full lg:w-auto">
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-3 sm:p-4 border border-gray-200">
                        <p class="text-xs sm:text-sm text-gray-600 mb-1">Today</p>
                        <p class="text-sm sm:text-lg font-semibold text-gray-800"><?php echo date('l, F j'); ?></p>
                        <p class="text-lg sm:text-2xl font-bold text-orange-600"><?php echo date('g:i A'); ?></p>
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
                    <p class="text-gray-600 text-sm sm:text-base">Choose your department to see available teachers and ensure proper consultation matching.</p>
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
                    <span class="hidden sm:inline">Show All Teachers</span>
                    <span class="sm:hidden">All Teachers</span>
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

        <!-- Teachers Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <?php if (empty($teachers)): ?>
                <div class="col-span-full bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Teachers Available</h3>
                    <p class="text-gray-500 mb-4">No teachers are currently available for consultation.</p>
                    <a href="index.php" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-seait-dark transition-colors">
                        Try Different Department
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($teachers as $teacher): ?>
                    <div class="teacher-card bg-white rounded-xl shadow-lg hover:shadow-2xl cursor-pointer transform hover:scale-105 transition-all duration-300 border border-gray-200 flex flex-col overflow-hidden" 
                         data-teacher-id="<?php echo htmlspecialchars($teacher['id']); ?>"
                         data-teacher-name="<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>"
                         data-teacher-dept="<?php echo htmlspecialchars($teacher['department']); ?>">
                        
                        <!-- Enhanced Card Header with Avatar -->
                        <div class="p-4 sm:p-8 border-b border-gray-100 flex-shrink-0 bg-gradient-to-br from-gray-50 to-white">
                            <div class="flex items-center space-x-4 sm:space-x-6">
                                <!-- Enhanced Teacher Avatar -->
                                <div class="flex-shrink-0 teacher-avatar">
                                    <?php if ($teacher['image_url']): ?>
                                        <img src="../<?php echo htmlspecialchars($teacher['image_url']); ?>" 
                                             alt="Teacher" class="w-16 h-16 sm:w-20 sm:h-20 rounded-full object-cover border-4 border-orange-500 shadow-lg">
                                    <?php else: ?>
                                        <?php
                                        // Generate initials from first and last name
                                        $first_initial = strtoupper(substr($teacher['first_name'], 0, 1));
                                        $last_initial = strtoupper(substr($teacher['last_name'], 0, 1));
                                        $initials = $first_initial . $last_initial;
                                        ?>
                                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center border-4 border-orange-500 shadow-lg">
                                            <span class="text-white text-lg sm:text-2xl font-bold"><?php echo htmlspecialchars($initials); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Enhanced Teacher Name and Status -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg sm:text-xl font-bold text-gray-800 mb-2 truncate">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </h3>
                                    <div class="flex items-center space-x-2 sm:space-x-3 mb-2 sm:mb-3">
                                        <div class="status-badge w-3 h-3 sm:w-4 sm:h-4 rounded-full bg-green-500"></div>
                                        <span class="text-xs sm:text-sm text-green-600 font-semibold">Available for Consultation</span>
                                    </div>
                                    <div class="flex items-center text-gray-600 text-xs sm:text-sm">
                                        <i class="fas fa-graduation-cap mr-1 sm:mr-2 text-orange-500"></i>
                                        <span class="truncate"><?php echo htmlspecialchars($teacher['department']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Card Footer -->
                        <div class="px-4 sm:px-8 py-4 sm:py-6 bg-gradient-to-r from-orange-50 to-orange-100 rounded-b-xl flex-shrink-0">
                            <div class="flex items-center justify-between">
                                <div class="text-center flex-1">
                                    <div class="text-xs sm:text-sm text-orange-700 font-medium mb-2">Tap to start consultation</div>
                                    <div class="flex items-center justify-center space-x-1 sm:space-x-2">
                                        <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse"></div>
                                        <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                                        <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                                    </div>
                                </div>
                                <div class="text-orange-600 text-lg sm:text-xl">
                                    <i class="fas fa-arrow-right transform hover:translate-x-1 transition-transform"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

        <!-- Notification -->
        <div id="notification" class="notification" style="display: none;">
            <div class="flex items-center">
                <i class="fas fa-bell mr-2"></i>
                <span id="notificationText">Teacher notified successfully!</span>
            </div>
        </div>
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

    <script>
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            
            try {
            
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

        // Auto-notify teacher when student taps teacher card
        const teacherCards = document.querySelectorAll('.teacher-card');
        const loadingState = document.getElementById('loadingState');
        const notification = document.getElementById('notification');
        const notificationText = document.getElementById('notificationText');
        
        console.log('Found teacher cards:', teacherCards.length);
        console.log('Loading state element:', loadingState);
        console.log('Notification element:', notification);
        console.log('Notification text element:', notificationText);

        teacherCards.forEach((card, index) => {
            console.log(`Setting up click listener for card ${index + 1}:`, card);
            card.addEventListener('click', function(e) {
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
            });
        });





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
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
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
                    
                    if (data.status === 'accepted') {
                        console.log('Consultation accepted! Stopping status checking...');
                        stopStatusChecking();
                        hidePendingRequest();
                        showConsultationResponse('accepted', data.teacher_name);
                        // Clear the session ID to prevent further checking
                        sessionStorage.removeItem('currentSessionId');
                    } else if (data.status === 'declined') {
                        console.log('Consultation declined! Stopping status checking...');
                        stopStatusChecking();
                        hidePendingRequest();
                        showConsultationResponse('declined', data.teacher_name);
                        // Clear the session ID to prevent further checking
                        sessionStorage.removeItem('currentSessionId');
                    } else if (data.status === 'pending') {
                        console.log('Status still pending, continuing to check...');
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
                formData.append('student_name', 'Student');
                
                // Use selected department from URL or teacher's department as fallback
                const selectedDept = '<?php echo htmlspecialchars($selected_department); ?>';
                const studentDept = selectedDept || teacherDept || 'General';
                formData.append('student_dept', studentDept);
                formData.append('student_id', '');
                
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
        
        // Enhanced consultation response notification with audio - following teacher screen pattern
        function showConsultationResponse(response, teacherName) {
            // Log to console for debugging
            if (response === 'accepted') {
                console.log(`🎉 Consultation Accepted! ${teacherName} has accepted your consultation request.`);
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
                        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-green-200 bg-gradient-to-r from-green-50 to-green-100 rounded-t-2xl">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                                    <i class="fas fa-check-circle text-white text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg sm:text-xl font-bold text-gray-800">Consultation Accepted</h3>
                                    <p class="text-xs sm:text-sm text-green-600 font-medium">Success!</p>
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
                                    <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg animate-pulse">
                                        <i class="fas fa-check-circle text-white text-3xl"></i>
                                    </div>
                                    <h4 class="text-xl sm:text-2xl font-bold text-gray-800 mb-3">🎉 Consultation Accepted!</h4>
                                    <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                                        <strong>${teacherName}</strong> has accepted your consultation request.
                                    </p>
                                    <div class="bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-xl p-4 mb-4">
                                        <div class="flex items-center justify-center space-x-2 mb-2">
                                            <i class="fas fa-info-circle text-green-600"></i>
                                            <span class="text-sm font-semibold text-green-800">Ready to Start</span>
                                        </div>
                                        <p class="text-xs sm:text-sm text-green-700">
                                            You can now proceed with your consultation session. The teacher will be notified of your acceptance.
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center space-x-4 text-xs sm:text-sm text-gray-500">
                                        <span class="flex items-center">
                                            <i class="fas fa-clock mr-1"></i>
                                            Real-time
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
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Modal Footer -->
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 p-4 sm:p-6 border-t border-green-200 bg-gradient-to-r from-green-50 to-green-100 rounded-b-2xl">
                            <button onclick="stopResponseAudio()"
                                    class="flex-1 bg-gradient-to-r from-red-500 to-red-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-red-600 hover:to-red-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
                                <i class="fas fa-volume-mute mr-2"></i>Stop Audio
                            </button>
                            <button data-action="close-modal"
                                    class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-green-600 hover:to-green-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 shadow-lg transform hover:scale-105">
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
            
            // Auto-close after 10 seconds
            setTimeout(() => {
                closeConsultationModal();
            }, 10000);
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
            // Refresh the page to show available teachers
            window.location.reload();
        }
        
        // Make function globally accessible
        window.tryAnotherTeacher = tryAnotherTeacher;
        
        // Show pending request indicator
        function showPendingRequest(teacherName) {
            // Remove any existing pending indicator
            const existingIndicator = document.getElementById('pendingRequestIndicator');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            const pendingIndicator = document.createElement('div');
            pendingIndicator.id = 'pendingRequestIndicator';
            pendingIndicator.className = 'fixed bottom-6 right-6 z-50 p-6 rounded-2xl shadow-2xl bg-gradient-to-r from-orange-500 to-orange-600 text-white animate-pulse border border-orange-400 max-w-sm';
            pendingIndicator.innerHTML = `
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-lg mb-1">⏳ Waiting for Response</h4>
                        <p class="text-sm text-orange-100 mb-3">Waiting for <strong>${teacherName}</strong> to respond to your consultation request...</p>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-orange-100">Checking every 500ms...</span>
                            <div class="flex space-x-1">
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></div>
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" style="animation-delay: 0.1s;"></div>
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" style="animation-delay: 0.3s;"></div>
                                <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                            </div>
                        </div>
                        <div class="text-xs text-orange-100">
                            <i class="fas fa-bolt mr-1"></i>Fast response monitoring active
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
        }
        
        function hidePendingRequest() {
            const pendingIndicator = document.getElementById('pendingRequestIndicator');
            if (pendingIndicator) {
                pendingIndicator.remove();
            }
            
            // Re-enable all teacher cards
            const teacherCards = document.querySelectorAll('.teacher-card');
            teacherCards.forEach(card => {
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
                card.style.cursor = 'pointer';
            });
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
            const notification = document.getElementById('notification');
            
            if (loadingState) {
                loadingState.style.display = 'none';
                console.log('Loading state hidden');
            }
            if (notification) {
                notification.style.display = 'none';
                console.log('Notification hidden');
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
            }
        });
        
            } catch (error) {
                console.error('Error in DOMContentLoaded:', error);
            }
        });
        
        // Add error handling
        window.addEventListener('error', function(e) {
            console.error('JavaScript error:', e.message, 'at', e.filename, 'line', e.lineno);
        });
    </script>
</body>
</html>
