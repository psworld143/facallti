<?php
session_start();
require_once '../config/database.php';

// Set page title
$page_title = 'Student-Teacher Consultation';

// Initialize arrays
$all_departments = [];

// Get departments from heads table with error handling
$heads_departments_query = "SELECT DISTINCT department FROM heads WHERE status = 'active' ORDER BY department";
$heads_departments_result = mysqli_query($conn, $heads_departments_query);

if ($heads_departments_result && mysqli_num_rows($heads_departments_result) > 0) {
    while ($row = mysqli_fetch_assoc($heads_departments_result)) {
        $all_departments[] = [
            'id' => null,
            'name' => $row['department'],
            'description' => 'Department consultation services',
            'icon' => 'fas fa-building',
            'color_theme' => '#FF6B35'
        ];
    }
}

// If no departments from heads table, add default ones
if (empty($all_departments)) {
    $all_departments = [
        [
            'id' => 1,
            'name' => 'Computer Science',
            'description' => 'Computer Science Department consultation services',
            'icon' => 'fas fa-laptop-code',
            'color_theme' => '#FF6B35'
        ],
        [
            'id' => 2,
            'name' => 'Mathematics',
            'description' => 'Mathematics Department consultation services',
            'icon' => 'fas fa-calculator',
            'color_theme' => '#2C3E50'
        ],
        [
            'id' => 3,
            'name' => 'English',
            'description' => 'English Department consultation services',
            'icon' => 'fas fa-book',
            'color_theme' => '#3498DB'
        ],
        [
            'id' => 4,
            'name' => 'College of Information and Communication Technology',
            'description' => 'ICT College consultation services',
            'icon' => 'fas fa-network-wired',
            'color_theme' => '#E74C3C'
        ],
        [
            'id' => 5,
            'name' => 'College of Business and Good Governance',
            'description' => 'Business College consultation services',
            'icon' => 'fas fa-chart-line',
            'color_theme' => '#27AE60'
        ],
        [
            'id' => 6,
            'name' => 'History',
            'description' => 'History Department consultation services',
            'icon' => 'fas fa-landmark',
            'color_theme' => '#9B59B6'
        ]
    ];
}

// Define available screens
$available_screens = [
    [
        'id' => 'student_screen',
        'name' => 'Student Screen',
        'description' => 'Browse available teachers and start consultations',
        'icon' => 'fas fa-user-graduate',
        'color_theme' => '#10B981',
        'url' => 'student-screen.php'
    ],
    [
        'id' => 'teacher_screen',
        'name' => 'Teacher Screen',
        'description' => 'Office standby screen for receiving consultation requests',
        'icon' => 'fas fa-user-tie',
        'color_theme' => '#3B82F6',
        'url' => 'teacher-screen.php'
    ]
];
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease-in-out;
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .selection-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .selection-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .selection-card:active {
            transform: translateY(0);
        }

        .selection-card.selected {
            border-color: #FF6B35;
            background-color: #FFF8F0;
        }

        .loading {
            display: none;
        }

        .loading.show {
            display: block;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            align-items: center;
            margin: 0 1rem;
        }

        .step-number {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .step.active .step-number {
            background-color: #FF6B35;
            color: white;
        }

        .step.completed .step-number {
            background-color: #10B981;
            color: white;
        }

        .step.inactive .step-number {
            background-color: #E5E7EB;
            color: #6B7280;
        }

        .step-line {
            width: 3rem;
            height: 2px;
            background-color: #E5E7EB;
            margin: 0 0.5rem;
        }

        .step-line.active {
            background-color: #FF6B35;
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
                        <h1 class="text-xl font-bold text-seait-dark">Student-Teacher Consultation</h1>
                        <p class="text-sm text-gray-600">Select your department and screen to get started</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-seait-orange hover:text-seait-dark transition-colors">
                        <i class="fas fa-home mr-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-seait-orange rounded-full mb-6">
                <i class="fas fa-user-graduate text-white text-3xl"></i>
            </div>
            <h2 class="text-3xl font-bold text-seait-dark mb-4">Student-Teacher Consultation Portal</h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Connect with teachers for academic guidance and support. 
                Select your department and choose your screen to get started.
            </p>
        </div>

        <!-- Selection Button -->
        <div class="text-center mb-12">
            <button id="startSelectionBtn" class="bg-seait-orange hover:bg-seait-dark text-white font-semibold py-4 px-8 rounded-lg text-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                <i class="fas fa-cog mr-3"></i>
                Start Selection Process
            </button>
        </div>

        <!-- Features Section -->
        <div class="grid md:grid-cols-3 gap-8 mb-12">
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                    <i class="fas fa-building text-blue-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-seait-dark mb-2">Department Selection</h3>
                <p class="text-gray-600">Choose your academic department to find relevant teachers.</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <i class="fas fa-desktop text-green-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-seait-dark mb-2">Screen Selection</h3>
                <p class="text-gray-600">Select the appropriate screen for your role.</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-100 rounded-full mb-4">
                    <i class="fas fa-comments text-purple-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-seait-dark mb-2">Start Consultation</h3>
                <p class="text-gray-600">Connect and start your consultation session.</p>
            </div>
        </div>

        <!-- Access Options -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-12">
            <h3 class="text-2xl font-bold text-seait-dark mb-6 text-center">Quick Access</h3>
            <div class="grid md:grid-cols-2 gap-8">
                <div class="text-center p-6 border-2 border-gray-200 rounded-lg hover:border-seait-orange transition-colors">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                        <i class="fas fa-user-graduate text-green-600 text-xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-seait-dark mb-2">Student Access</h4>
                    <p class="text-gray-600 mb-4">Browse available teachers and start consultations</p>
                    <a href="student-screen.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors">
                        Student Portal
                    </a>
                </div>
                <div class="text-center p-6 border-2 border-gray-200 rounded-lg hover:border-seait-orange transition-colors">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                        <i class="fas fa-user-tie text-blue-600 text-xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-seait-dark mb-2">Teacher Access</h4>
                    <p class="text-gray-600 mb-4">Office standby screen for receiving consultation requests</p>
                    <a href="teacher-screen.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        Teacher Portal
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Selection Modal -->
    <div id="selectionModal" class="modal">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="bg-seait-orange text-white px-6 py-4 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-semibold">
                        <i class="fas fa-cog mr-2"></i>
                        Select Department & Screen
                    </h3>
                    <button id="closeModal" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" id="step1">
                        <div class="step-number">1</div>
                        <span class="text-sm font-medium">Department</span>
                    </div>
                    <div class="step-line" id="stepLine1"></div>
                    <div class="step inactive" id="step2">
                        <div class="step-number">2</div>
                        <span class="text-sm font-medium">Screen</span>
                    </div>
                </div>

                <!-- Step 1: Department Selection -->
                <div id="step1Content">
                    <div class="text-center mb-6">
                        <h4 class="text-lg font-semibold text-seait-dark mb-4">Select Your Department</h4>
                        <p class="text-gray-600">Choose your academic department to access consultation services.</p>
                    </div>
                    
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php if (empty($all_departments)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-4"></i>
                                <p class="text-gray-600">No departments available at the moment.</p>
                                <p class="text-sm text-gray-500 mt-2">Please contact the administrator.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($all_departments as $department): ?>
                                <div class="selection-card bg-white border-2 border-gray-200 rounded-lg p-4 hover:border-seait-orange transition-all duration-300" 
                                     data-type="department"
                                     data-value="<?php echo htmlspecialchars($department['name']); ?>"
                                     data-id="<?php echo htmlspecialchars($department['id'] ?? ''); ?>"
                                     style="min-height: 80px;">
                                    <div class="flex items-center h-full">
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 rounded-lg flex items-center justify-center" 
                                                 style="background-color: <?php echo htmlspecialchars($department['color_theme']); ?>20;">
                                                <i class="<?php echo htmlspecialchars($department['icon']); ?> text-xl" 
                                                   style="color: <?php echo htmlspecialchars($department['color_theme']); ?>;"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <h4 class="text-lg font-semibold text-seait-dark mb-1">
                                                <?php echo htmlspecialchars($department['name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600 leading-relaxed">
                                                <?php echo htmlspecialchars($department['description']); ?>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0 ml-4">
                                            <i class="fas fa-chevron-right text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 2: Screen Selection -->
                <div id="step2Content" class="hidden">
                    <div class="text-center mb-6">
                        <h4 class="text-lg font-semibold text-seait-dark mb-4">Select Your Screen</h4>
                        <p class="text-gray-600">Choose the appropriate screen for your role.</p>
                    </div>
                    
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($available_screens as $screen): ?>
                            <div class="selection-card bg-white border-2 border-gray-200 rounded-lg p-4 hover:border-seait-orange transition-all duration-300" 
                                 data-type="screen"
                                 data-value="<?php echo htmlspecialchars($screen['name']); ?>"
                                 data-id="<?php echo htmlspecialchars($screen['id']); ?>"
                                 data-url="<?php echo htmlspecialchars($screen['url']); ?>"
                                 style="min-height: 80px;">
                                <div class="flex items-center h-full">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center" 
                                             style="background-color: <?php echo htmlspecialchars($screen['color_theme']); ?>20;">
                                            <i class="<?php echo htmlspecialchars($screen['icon']); ?> text-xl" 
                                               style="color: <?php echo htmlspecialchars($screen['color_theme']); ?>;"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h4 class="text-lg font-semibold text-seait-dark mb-1">
                                            <?php echo htmlspecialchars($screen['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600 leading-relaxed">
                                            <?php echo htmlspecialchars($screen['description']); ?>
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 ml-4">
                                        <i class="fas fa-chevron-right text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between items-center mt-6 pt-6 border-t border-gray-200" style="min-height: 60px;">
                    <div>
                        <button id="prevBtn" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors" style="display: none;">
                            <i class="fas fa-arrow-left mr-2"></i>Previous
                        </button>
                    </div>
                    <div class="flex space-x-4">
                        <button id="nextBtn" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-seait-dark transition-colors cursor-pointer" style="display: inline-block !important; pointer-events: auto !important; min-width: 100px; font-size: 16px; font-weight: bold; border: 2px solid #FF6B35; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                            <i class="fas fa-arrow-right mr-2"></i>Next
                        </button>
                    </div>
                </div>
                


                <!-- Loading State -->
                <div id="loadingState" class="loading text-center py-8">
                    <div class="inline-flex items-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-seait-orange"></div>
                        <span class="ml-3 text-gray-600">Redirecting to selected screen...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-seait-dark text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> SEAIT. All rights reserved.</p>
                <p class="text-gray-400 text-sm mt-2">Student-Teacher Consultation Portal</p>
            </div>
        </div>
    </footer>

    <script>
        // Modal functionality
        const modal = document.getElementById('selectionModal');
        const startBtn = document.getElementById('startSelectionBtn');
        const closeBtn = document.getElementById('closeModal');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const loadingState = document.getElementById('loadingState');
        const step1Content = document.getElementById('step1Content');
        const step2Content = document.getElementById('step2Content');
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const stepLine1 = document.getElementById('stepLine1');

        let currentStep = 1;
        let selectedDepartment = '';
        let selectedScreen = '';
        let selectedScreenUrl = '';

        // Show modal
        startBtn.addEventListener('click', function() {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Initialize the step display
            updateStepDisplay();
            
            // Force button visibility
            if (nextBtn) {
                nextBtn.style.display = 'inline-block';
                nextBtn.style.visibility = 'visible';
            }
        });

        // Close modal
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            resetSelection();
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                resetSelection();
            }
        });

        // Navigation buttons
        prevBtn.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                updateStepDisplay();
            }
        });

        nextBtn.addEventListener('click', function() {
            if (currentStep === 1) {
                if (selectedDepartment) {
                    currentStep++;
                    updateStepDisplay();
                } else {
                    showAutoAdvanceMessage('Please select a department first');
                }
            } else if (currentStep === 2) {
                if (selectedScreen) {
                    proceedToScreen();
                } else {
                    showAutoAdvanceMessage('Please select a screen first');
                }
            }
        });

        // Update step display
        function updateStepDisplay() {
            if (currentStep === 1) {
                step1Content.classList.remove('hidden');
                step2Content.classList.add('hidden');
                step1.classList.remove('completed');
                step1.classList.add('active');
                step2.classList.remove('active');
                step2.classList.add('inactive');
                stepLine1.classList.remove('active');
                prevBtn.classList.add('hidden');
                nextBtn.classList.remove('hidden'); // Ensure Next button is visible
                nextBtn.innerHTML = 'Next<i class="fas fa-arrow-right ml-2"></i>';
            } else if (currentStep === 2) {
                step1Content.classList.add('hidden');
                step2Content.classList.remove('hidden');
                step1.classList.remove('active');
                step1.classList.add('completed');
                step2.classList.remove('inactive');
                step2.classList.add('active');
                stepLine1.classList.add('active');
                prevBtn.classList.remove('hidden');
                nextBtn.classList.remove('hidden'); // Ensure Proceed button is visible
                nextBtn.innerHTML = 'Proceed<i class="fas fa-arrow-right ml-2"></i>';
                
                // Show auto-advance message
                //showAutoAdvanceMessage('Now select your screen');
            }
        }
        
        // Show auto-advance message
        function showAutoAdvanceMessage(message) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            messageDiv.innerHTML = `<i class="fas fa-check mr-2"></i>${message}`;
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.remove();
            }, 3000);
        }

        // Selection cards - use event delegation to handle all cards
        document.addEventListener('click', function(e) {
            if (e.target.closest('.selection-card')) {
                const card = e.target.closest('.selection-card');
                const type = card.getAttribute('data-type');
                const value = card.getAttribute('data-value');
                const id = card.getAttribute('data-id');

                // Remove selection from other cards of same type
                document.querySelectorAll('.selection-card[data-type="' + type + '"]').forEach(c => {
                    c.classList.remove('selected');
                });

                // Add selection to current card
                card.classList.add('selected');

                if (type === 'department') {
                    selectedDepartment = value;
                    
                    // Show selection feedback
                    //showAutoAdvanceMessage(`Selected: ${selectedDepartment}`);
                    
                    // Auto-advance to step 2 after a short delay
                    setTimeout(() => {
                        currentStep++;
                        updateStepDisplay();
                    }, 800); // 800ms delay for better UX
                    
                } else if (type === 'screen') {
                    selectedScreen = value;
                    selectedScreenUrl = card.getAttribute('data-url');
                    
                    // Auto-proceed to selected screen after a short delay
                    setTimeout(() => {
                        proceedToScreen();
                    }, 800); // 800ms delay for better UX
                }
            }
        });

        // Proceed to selected screen
        function proceedToScreen() {
            loadingState.classList.add('show');
            
            // Store selections in session storage
            sessionStorage.setItem('selectedDepartment', selectedDepartment);
            sessionStorage.setItem('selectedScreen', selectedScreen);
            
            // Redirect to selected screen with department parameter
            setTimeout(() => {
                const url = selectedScreenUrl + '?dept=' + encodeURIComponent(selectedDepartment);
                window.location.href = url;
            }, 1500);
        }

        // Reset selection
        function resetSelection() {
            currentStep = 1;
            selectedDepartment = '';
            selectedScreen = '';
            selectedScreenUrl = '';
            document.querySelectorAll('.selection-card').forEach(card => {
                card.classList.remove('selected');
            });
            updateStepDisplay();
            loadingState.classList.remove('show');
        }

        // Check if user already has selections
        window.addEventListener('load', function() {
            const savedDept = sessionStorage.getItem('selectedDepartment');
            const savedScreen = sessionStorage.getItem('selectedScreen');
            if (savedDept && savedScreen) {
                // User has previous selections
            }
        });
    </script>
</body>
</html>
