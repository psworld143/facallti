<?php
session_start();
require_once '../config/database.php';

// Set page title
$page_title = 'Student Consultation';

// Get selected department from session or URL parameter
$selected_department = $_GET['dept'] ?? sessionStorage.getItem('selectedDepartment') ?? '';

// Get teachers available for consultation today
$today = date('Y-m-d');
$teachers_query = "SELECT DISTINCT 
                    f.id,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position,
                    f.email,
                    f.phone,
                    f.consultation_hours,
                    f.consultation_location,
                    f.status,
                    f.profile_image
                   FROM faculty f 
                   WHERE f.status = 'active' 
                   AND f.department = ?
                   AND f.consultation_hours IS NOT NULL
                   AND f.consultation_hours != ''
                   ORDER BY f.first_name, f.last_name";

$teachers_stmt = mysqli_prepare($conn, $teachers_query);
mysqli_stmt_bind_param($teachers_stmt, "s", $selected_department);
mysqli_stmt_execute($teachers_stmt);
$teachers_result = mysqli_stmt_get_result($teachers_stmt);

$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_result)) {
    $teachers[] = $row;
}

// If no teachers found for the department, get all available teachers
if (empty($teachers)) {
    $fallback_query = "SELECT DISTINCT 
                        f.id,
                        f.first_name,
                        f.last_name,
                        f.department,
                        f.position,
                        f.email,
                        f.phone,
                        f.consultation_hours,
                        f.consultation_location,
                        f.status,
                        f.profile_image
                       FROM faculty f 
                       WHERE f.status = 'active' 
                       AND f.consultation_hours IS NOT NULL
                       AND f.consultation_hours != ''
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
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
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
        .teacher-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .teacher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .teacher-card:active {
            transform: translateY(0);
        }

        .status-online {
            background-color: #10B981;
        }

        .status-busy {
            background-color: #F59E0B;
        }

        .status-offline {
            background-color: #6B7280;
        }

        .loading {
            display: none;
        }

        .loading.show {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-12 w-auto">
                    <div class="ml-4">
                        <h1 class="text-xl font-bold text-seait-dark">Student Consultation</h1>
                        <p class="text-sm text-gray-600">
                            <?php if ($selected_department): ?>
                                Department: <?php echo htmlspecialchars($selected_department); ?>
                            <?php else: ?>
                                Available Teachers
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-seait-orange hover:text-seait-dark transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Selection
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-seait-dark mb-2">Available Teachers Today</h2>
                    <p class="text-gray-600">Select a teacher to start a consultation session</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500"><?php echo date('l, F j, Y'); ?></p>
                    <p class="text-sm text-gray-500"><?php echo date('g:i A'); ?></p>
                </div>
            </div>
        </div>

        <!-- Department Filter -->
        <?php if (empty($selected_department)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-seait-dark mb-4">Filter by Department</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button class="department-filter-btn bg-seait-orange text-white px-4 py-2 rounded-lg text-sm font-medium" data-dept="Computer Science">
                    Computer Science
                </button>
                <button class="department-filter-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium" data-dept="Mathematics">
                    Mathematics
                </button>
                <button class="department-filter-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium" data-dept="English">
                    English
                </button>
                <button class="department-filter-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium" data-dept="College of Information and Communication Technology">
                    ICT College
                </button>
                <button class="department-filter-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium" data-dept="College of Business and Good Governance">
                    Business College
                </button>
                <button class="department-filter-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium" data-dept="History">
                    History
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Teachers List -->
        <div class="space-y-4">
            <?php if (empty($teachers)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Teachers Available</h3>
                    <p class="text-gray-500 mb-4">No teachers are currently available for consultation.</p>
                    <a href="index.php" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-seait-dark transition-colors">
                        Try Different Department
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($teachers as $teacher): ?>
                    <div class="teacher-card bg-white rounded-lg shadow-md p-6 border-l-4 border-seait-orange" 
                         data-teacher-id="<?php echo htmlspecialchars($teacher['id']); ?>"
                         data-teacher-name="<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>"
                         data-teacher-dept="<?php echo htmlspecialchars($teacher['department']); ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <!-- Teacher Avatar -->
                                <div class="flex-shrink-0">
                                    <?php if ($teacher['profile_image']): ?>
                                        <img src="../uploads/faculty/<?php echo htmlspecialchars($teacher['profile_image']); ?>" 
                                             alt="Teacher" class="w-16 h-16 rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-white text-xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Teacher Info -->
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-seait-dark">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm">
                                        <?php echo htmlspecialchars($teacher['position']); ?>
                                    </p>
                                    <p class="text-gray-500 text-sm">
                                        <?php echo htmlspecialchars($teacher['department']); ?>
                                    </p>
                                    
                                    <!-- Consultation Details -->
                                    <div class="mt-2 flex items-center space-x-4 text-sm">
                                        <?php if ($teacher['consultation_hours']): ?>
                                            <span class="flex items-center text-gray-600">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo htmlspecialchars($teacher['consultation_hours']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($teacher['consultation_location']): ?>
                                            <span class="flex items-center text-gray-600">
                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                <?php echo htmlspecialchars($teacher['consultation_location']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Status and Action -->
                            <div class="flex items-center space-x-4">
                                <!-- Status Indicator -->
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 rounded-full status-online"></div>
                                    <span class="text-sm text-green-600 font-medium">Available</span>
                                </div>

                                <!-- Contact Info -->
                                <div class="flex items-center space-x-2">
                                    <?php if ($teacher['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($teacher['email']); ?>" 
                                           class="text-gray-400 hover:text-seait-orange transition-colors">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($teacher['phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($teacher['phone']); ?>" 
                                           class="text-gray-400 hover:text-seait-orange transition-colors">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Start Consultation Button -->
                                <button class="start-consultation-btn bg-seait-orange hover:bg-seait-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                    <i class="fas fa-video mr-2"></i>
                                    Start Consultation
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="loading fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 flex items-center space-x-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-seait-orange"></div>
                <span class="text-gray-600">Connecting to consultation...</span>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-seait-dark text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> SEAIT. All rights reserved.</p>
                <p class="text-gray-400 text-sm mt-2">Student Consultation Portal</p>
            </div>
        </div>
    </footer>

    <script>
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
                window.location.href = `student-consultation.php?dept=${encodeURIComponent(department)}`;
            });
        });

        // Start consultation functionality
        const startConsultationBtns = document.querySelectorAll('.start-consultation-btn');
        const loadingState = document.getElementById('loadingState');

        startConsultationBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const teacherCard = this.closest('.teacher-card');
                const teacherId = teacherCard.getAttribute('data-teacher-id');
                const teacherName = teacherCard.getAttribute('data-teacher-name');
                const teacherDept = teacherCard.getAttribute('data-teacher-dept');
                
                // Show loading state
                loadingState.classList.add('show');
                
                // Store teacher info in session storage
                sessionStorage.setItem('consultationTeacherId', teacherId);
                sessionStorage.setItem('consultationTeacherName', teacherName);
                sessionStorage.setItem('consultationTeacherDept', teacherDept);
                
                // Redirect to consultation call page
                setTimeout(() => {
                    window.location.href = `consultation-call.php?teacher_id=${teacherId}`;
                }, 1500);
            });
        });

        // Teacher card click functionality
        const teacherCards = document.querySelectorAll('.teacher-card');
        teacherCards.forEach(card => {
            card.addEventListener('click', function() {
                const teacherId = this.getAttribute('data-teacher-id');
                const teacherName = this.getAttribute('data-teacher-name');
                const teacherDept = this.getAttribute('data-teacher-dept');
                
                // Store teacher info
                sessionStorage.setItem('consultationTeacherId', teacherId);
                sessionStorage.setItem('consultationTeacherName', teacherName);
                sessionStorage.setItem('consultationTeacherDept', teacherDept);
                
                // Redirect to consultation call page
                window.location.href = `consultation-call.php?teacher_id=${teacherId}`;
            });
        });
    </script>
</body>
</html>
