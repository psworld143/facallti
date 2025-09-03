<?php
session_start();
require_once '../config/database.php';

// Set page title
$page_title = 'Faculty Consultation';

// Get selected department from URL parameter
$selected_department = $_GET['dept'] ?? '';

// Get department heads and administrators for the selected department
$heads_query = "SELECT 
                    h.id,
                    h.user_id,
                    h.department,
                    h.position,
                    h.phone,
                    h.status,
                    u.first_name,
                    u.last_name,
                    u.email
                   FROM heads h 
                   INNER JOIN users u ON h.user_id = u.id
                   WHERE h.status = 'active' 
                   AND h.department = ?
                   ORDER BY h.position, u.first_name, u.last_name";

$heads_stmt = mysqli_prepare($conn, $heads_query);
mysqli_stmt_bind_param($heads_stmt, "s", $selected_department);
mysqli_stmt_execute($heads_stmt);
$heads_result = mysqli_stmt_get_result($heads_stmt);

$heads = [];
while ($row = mysqli_fetch_assoc($heads_result)) {
    $heads[] = $row;
}

// If no heads found for the department, get all available heads
if (empty($heads)) {
    $fallback_query = "SELECT 
                        h.id,
                        h.user_id,
                        h.department,
                        h.position,
                        h.phone,
                        h.status,
                        u.first_name,
                        u.last_name,
                        u.email
                       FROM heads h 
                       INNER JOIN users u ON h.user_id = u.id
                       WHERE h.status = 'active' 
                       ORDER BY h.department, h.position, u.first_name, u.last_name";
    
    $fallback_result = mysqli_query($conn, $fallback_query);
    while ($row = mysqli_fetch_assoc($fallback_result)) {
        $heads[] = $row;
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
        .head-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .head-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .head-card:active {
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
                        <h1 class="text-xl font-bold text-seait-dark">Faculty Consultation</h1>
                        <p class="text-sm text-gray-600">
                            <?php if ($selected_department): ?>
                                Department: <?php echo htmlspecialchars($selected_department); ?>
                            <?php else: ?>
                                Available Department Heads
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
                    <h2 class="text-2xl font-bold text-seait-dark mb-2">Department Heads & Administrators</h2>
                    <p class="text-gray-600">Schedule meetings with department heads and administrators for faculty matters</p>
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

        <!-- Heads List -->
        <div class="space-y-4">
            <?php if (empty($heads)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Department Heads Available</h3>
                    <p class="text-gray-500 mb-4">No department heads are currently available for consultation.</p>
                    <a href="index.php" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-seait-dark transition-colors">
                        Try Different Department
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($heads as $head): ?>
                    <div class="head-card bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500" 
                         data-head-id="<?php echo htmlspecialchars($head['id']); ?>"
                         data-head-name="<?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?>"
                         data-head-dept="<?php echo htmlspecialchars($head['department']); ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <!-- Head Avatar -->
                                <div class="flex-shrink-0">
                                    <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user-tie text-white text-xl"></i>
                                    </div>
                                </div>

                                <!-- Head Info -->
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-seait-dark">
                                        <?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm">
                                        <?php echo htmlspecialchars($head['position']); ?>
                                    </p>
                                    <p class="text-gray-500 text-sm">
                                        <?php echo htmlspecialchars($head['department']); ?>
                                    </p>
                                    
                                    <!-- Contact Details -->
                                    <div class="mt-2 flex items-center space-x-4 text-sm">
                                        <?php if ($head['email']): ?>
                                            <span class="flex items-center text-gray-600">
                                                <i class="fas fa-envelope mr-1"></i>
                                                <?php echo htmlspecialchars($head['email']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($head['phone']): ?>
                                            <span class="flex items-center text-gray-600">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($head['phone']); ?>
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
                                    <?php if ($head['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($head['email']); ?>" 
                                           class="text-gray-400 hover:text-seait-orange transition-colors">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($head['phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($head['phone']); ?>" 
                                           class="text-gray-400 hover:text-seait-orange transition-colors">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Schedule Meeting Button -->
                                <button class="schedule-meeting-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                    <i class="fas fa-calendar-plus mr-2"></i>
                                    Schedule Meeting
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
                <span class="text-gray-600">Scheduling meeting...</span>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-seait-dark text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> SEAIT. All rights reserved.</p>
                <p class="text-gray-400 text-sm mt-2">Faculty Consultation Portal</p>
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
                window.location.href = `faculty-consultation.php?dept=${encodeURIComponent(department)}`;
            });
        });

        // Schedule meeting functionality
        const scheduleMeetingBtns = document.querySelectorAll('.schedule-meeting-btn');
        const loadingState = document.getElementById('loadingState');

        scheduleMeetingBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const headCard = this.closest('.head-card');
                const headId = headCard.getAttribute('data-head-id');
                const headName = headCard.getAttribute('data-head-name');
                const headDept = headCard.getAttribute('data-head-dept');
                
                // Show loading state
                loadingState.classList.add('show');
                
                // Store head info in session storage
                sessionStorage.setItem('selectedHeadId', headId);
                sessionStorage.setItem('selectedHeadName', headName);
                sessionStorage.setItem('selectedHeadDept', headDept);
                
                // Simulate scheduling process
                setTimeout(() => {
                    alert(`Meeting request sent to ${headName} from ${headDept}.\n\nYou will receive a confirmation email shortly.`);
                    
                    // Hide loading
                    loadingState.classList.remove('show');
                }, 2000);
            });
        });

        // Head card click functionality
        const headCards = document.querySelectorAll('.head-card');
        headCards.forEach(card => {
            card.addEventListener('click', function() {
                const headId = this.getAttribute('data-head-id');
                const headName = this.getAttribute('data-head-name');
                const headDept = this.getAttribute('data-head-dept');
                
                // Store head info
                sessionStorage.setItem('selectedHeadId', headId);
                sessionStorage.setItem('selectedHeadName', headName);
                sessionStorage.setItem('selectedHeadDept', headDept);
                
                // Show head details modal or redirect to scheduling page
                alert(`Selected: ${headName}\nDepartment: ${headDept}\n\nClick "Schedule Meeting" to proceed.`);
            });
        });
    </script>
</body>
</html>
