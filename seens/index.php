<?php
// Include configuration first
include('configuration.php');
// Include database initialization script
include('database_init.php');
include('headers.php');

// Include and auto-start RFID services
include('rfid_service_manager.php');

// Enhanced database connection check with user permission
function checkAndInitializeDatabase() {
    global $conn, $host, $username, $password, $dbname;
    
    // Try to establish connection to the database
    $conn = establishConnection();
    
    // First, try to connect to the database
    if ($conn && !$conn->connect_error) {
        // Test if we can actually query the database
        $test_query = $conn->query("SELECT 1");
        if ($test_query !== false) {
            return true; // Database is working fine
        }
    }
    
    // Check if user has already given permission
    if (isset($_GET['allow_db_creation']) && $_GET['allow_db_creation'] === 'yes') {
        // User has given permission, proceed with initialization
        echo "<div style='position: fixed; top: 0; left: 0; right: 0; background: #fff7ed; border-bottom: 2px solid #f97316; padding: 10px; text-align: center; z-index: 9999; font-family: monospace;'>";
        echo "🔧 Database not found. Creating database and tables...<br>";
        
        // Use configuration variables from configuration.php
        if (function_exists('initializeDatabase')) {
            if (initializeDatabase($host, $username, $password, $dbname)) {
                echo "✅ Database and tables created successfully! Refreshing page...<br>";
                echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 3000);</script>";
                return true;
            } else {
                echo "❌ Database creation failed!<br>";
            }
        } else {
            echo "❌ Database initialization function not found!<br>";
        }
        
        echo "</div>";
        return false;
    }
    
    // Show permission request page
    return false;
}

// Check database connection and initialize if needed
if (!checkAndInitializeDatabase()) {
    // Show database setup modal instead of separate page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SEENS - Student Entry and Exit Notification System</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="assets/font-awesome/css/font-awesome.min.css">
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            'security': {
                                50: '#fff7ed',
                                100: '#ffedd5',
                                200: '#fed7aa',
                                300: '#fdba74',
                                400: '#fb923c',
                                500: '#f97316',
                                600: '#ea580c',
                                700: '#c2410c',
                                800: '#9a3412',
                                900: '#7c2d12',
                            }
                        }
                    }
                }
            }
        </script>
    </head>
    <body class="bg-gradient-to-br from-orange-50 via-amber-50 to-orange-100 min-h-screen">
        <!-- Main Content (will be hidden until database is ready) -->
        <div id="mainContent" class="hidden">
            <!-- Your existing SEENS content will go here -->
            <div class="max-w-7xl mx-auto p-4 sm:p-6">
                <div class="text-center mb-6 sm:mb-8 animate-fade-in">
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-800 mb-2">Student Entry and Exit Notification System</h1>
                    <div class="w-16 sm:w-24 h-1 bg-gradient-to-r from-orange-500 to-amber-600 mx-auto mt-4 rounded-full"></div>
                </div>
                <div class="text-center">
                    <p class="text-gray-600">System is ready. Database setup completed successfully!</p>
                </div>
            </div>
        </div>

        <!-- Database Setup Modal -->
        <div id="dbSetupModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-6 py-6 rounded-t-xl">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fa fa-database text-white text-3xl"></i>
                        </div>
                        <h1 class="text-2xl font-bold text-white mb-2">SEENS Database Setup</h1>
                        <p class="text-blue-100">Student Entry and Exit Notification System</p>
                    </div>
                </div>
                
                <div class="p-8">
                    <!-- Welcome Message -->
                    <div class="text-center mb-8">
                        <h2 class="text-xl font-semibold text-gray-800 mb-3">Welcome to SEENS!</h2>
                        <p class="text-gray-600">This appears to be your first time running the system. We need to set up the database to get started.</p>
                    </div>
                    
                    <!-- What will be created -->
                    <div class="bg-gradient-to-r from-orange-50 to-amber-50 rounded-lg p-6 mb-8 border border-orange-200">
                        <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fa fa-info-circle text-orange-500 mr-2"></i>
                            What will be created:
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                    <i class="fa fa-database text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Database</h4>
                                    <p class="text-sm text-gray-600">seait_seens</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                    <i class="fa fa-table text-green-600 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Tables</h4>
                                    <p class="text-sm text-gray-600">5 system tables</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                    <i class="fa fa-user text-purple-600 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Admin Account</h4>
                                    <p class="text-sm text-gray-600">Username: root (no password)</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                    <i class="fa fa-shield text-orange-600 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Security</h4>
                                    <p class="text-sm text-gray-600">Proper permissions & encryption</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Requirements Check -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-8">
                        <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fa fa-check-circle text-green-500 mr-2"></i>
                            System Requirements:
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700">XAMPP MySQL Service</span>
                                <span class="text-green-600 font-medium">✓ Running</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700">Database Permissions</span>
                                <span class="text-green-600 font-medium">✓ Available</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700">PHP MySQL Extension</span>
                                <span class="text-green-600 font-medium">✓ Installed</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <button id="createDbBtn" class="flex-1 bg-gradient-to-r from-orange-500 to-amber-600 text-white px-6 py-4 rounded-lg hover:from-orange-600 hover:to-amber-700 transition-all duration-300 font-semibold text-center transform hover:scale-105">
                            <i class="fa fa-rocket mr-2"></i>
                            Create Database & Tables
                        </button>
                        <button onclick="window.open('http://localhost/phpmyadmin', '_blank')" class="flex-1 bg-gray-100 text-gray-700 px-6 py-4 rounded-lg hover:bg-gray-200 transition-all duration-300 font-semibold">
                            <i class="fa fa-external-link mr-2"></i>
                            Open phpMyAdmin
                        </button>
                    </div>
                    
                    <!-- Additional Info -->
                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-500">
                            This process will take a few seconds. Please do not close this page during setup.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-[60] hidden">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fa fa-question-circle text-orange-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Confirm Database Creation</h3>
                        <p class="text-gray-600">Are you sure you want to create the database and tables? This action cannot be undone.</p>
                    </div>
                    
                    <div class="flex gap-3">
                        <button id="confirmYes" class="flex-1 bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors font-semibold">
                            Yes, Create Database
                        </button>
                        <button id="confirmNo" class="flex-1 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors font-semibold">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Modal -->
        <div id="progressModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-[70] hidden">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
                <div class="p-6">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fa fa-spinner fa-spin text-orange-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Creating Database...</h3>
                        <p class="text-gray-600 mb-4">Please wait while we set up your database and tables.</p>
                        <div class="bg-gray-200 rounded-full h-2 mb-4">
                            <div id="progressBar" class="bg-orange-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <p id="progressText" class="text-sm text-gray-500">Initializing...</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Database setup functionality
            document.addEventListener('DOMContentLoaded', function() {
                const createDbBtn = document.getElementById('createDbBtn');
                const confirmModal = document.getElementById('confirmModal');
                const progressModal = document.getElementById('progressModal');
                const dbSetupModal = document.getElementById('dbSetupModal');
                const mainContent = document.getElementById('mainContent');
                const confirmYes = document.getElementById('confirmYes');
                const confirmNo = document.getElementById('confirmNo');
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');

                // Show confirmation modal when Create Database button is clicked
                createDbBtn.addEventListener('click', function() {
                    confirmModal.classList.remove('hidden');
                });

                // Handle confirmation
                confirmYes.addEventListener('click', function() {
                    confirmModal.classList.add('hidden');
                    progressModal.classList.remove('hidden');
                    
                    // Simulate progress
                    let progress = 0;
                    const progressInterval = setInterval(function() {
                        progress += 10;
                        progressBar.style.width = progress + '%';
                        
                        if (progress <= 30) {
                            progressText.textContent = 'Connecting to MySQL...';
                        } else if (progress <= 60) {
                            progressText.textContent = 'Creating database...';
                        } else if (progress <= 80) {
                            progressText.textContent = 'Creating tables...';
                        } else if (progress <= 90) {
                            progressText.textContent = 'Setting up admin account...';
                        } else {
                            progressText.textContent = 'Finalizing setup...';
                        }
                        
                        if (progress >= 100) {
                            clearInterval(progressInterval);
                            // Redirect to create database
                            window.location.href = '?allow_db_creation=yes';
                        }
                    }, 200);
                });

                // Handle cancel
                confirmNo.addEventListener('click', function() {
                    confirmModal.classList.add('hidden');
                });

                // Close confirmation modal when clicking outside
                confirmModal.addEventListener('click', function(e) {
                    if (e.target === confirmModal) {
                        confirmModal.classList.add('hidden');
                    }
                });

                // Auto-refresh every 60 seconds to check if database was created manually
                setTimeout(function() {
                    location.reload();
                }, 60000);
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// If we reach here, database is working fine
// Establish database connection for main application
$conn = establishConnection();
if (!$conn || $conn->connect_error) {
    die("Database connection failed in main application");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEENS - Student Entry and Exit Notification System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'security': {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        bounceIn: {
                            '0%': { transform: 'scale(0.3)', opacity: '0' },
                            '50%': { transform: 'scale(1.05)' },
                            '70%': { transform: 'scale(0.9)' },
                            '100%': { transform: 'scale(1)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    <link rel="stylesheet" href="assets/sweet-alert/sweetalert2.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .btn-primary {
            background: linear-gradient(135deg, #ff8a65 0%, #ff7043 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 138, 101, 0.3);
        }
        .scanner-container {
            background: linear-gradient(135deg, #ff8a65 0%, #ff7043 100%);
            border-radius: 16px;
            padding: 2px;
        }
        .scanner-content {
            background: white;
            border-radius: 14px;
            padding: 1rem;
        }
        
        /* Responsive improvements */
        @media (max-width: 640px) {
            .scanner-container {
                border-radius: 12px;
            }
            .scanner-content {
                border-radius: 10px;
                padding: 0.75rem;
            }
            .card-hover:hover {
                transform: translateY(-2px);
            }
        }
        
        /* Prevent horizontal overflow */
        body {
            overflow-x: hidden;
        }
        
        /* Ensure table responsiveness */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile-friendly button sizes */
        @media (max-width: 640px) {
            .btn-primary {
                min-height: 44px; /* Touch-friendly minimum size */
            }
        }
        
        /* Ensure input text is fully visible */
        input[type="text"] {
            line-height: 1.5;
            vertical-align: middle;
        }
        
        /* Prevent text clipping in input fields */
        #barcode_search {
            min-height: 56px;
            box-sizing: border-box;
        }
        
        /* Ensure scan result text is fully visible */
        #scan_result {
            line-height: 1.2;
            display: block;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        
        /* Prevent tab button overlap */
        #pillNav2 {
            gap: 0.75rem;
        }
        
        #pillNav2 li {
            flex: 1;
            min-width: 0;
        }
        
        #pillNav2 .nav-link {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Loading animation improvements */
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        /* Smooth transitions for loading states */
        #loadingAnimation, #errorState, #emptyState {
            transition: all 0.3s ease-in-out;
        }
        
        /* Image loading fallback */
        .image-fallback {
            display: none;
        }
        
        img[src*="error"] + .image-fallback,
        img[src*="undefined"] + .image-fallback {
            display: flex;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-orange-50 via-amber-50 to-orange-100 min-h-screen">
	<div class="max-w-7xl mx-auto p-4 sm:p-6">
		
		<!-- Header Section -->
		<div class="text-center mb-6 sm:mb-8 animate-fade-in">
			<h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-800 mb-2">Student Entry and Exit Notification System</h1>
			<div class="w-16 sm:w-24 h-1 bg-gradient-to-r from-orange-500 to-amber-600 mx-auto mt-4 rounded-full"></div>
		</div>
		


		<div class="mt-6 sm:mt-8">
			        <div class="scanner-container mb-6 sm:mb-8 animate-slide-up">
            <div class="scanner-content p-4 sm:p-8">
                <ul class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4" id="pillNav2" role="tablist">
                    <li class="flex-1" role="presentation">
                        <a href="#home-tab2-reg" class="nav-link active block w-full text-center py-3 sm:py-4 px-4 sm:px-6 text-xs sm:text-sm font-semibold rounded-xl transition-all duration-300 bg-gradient-to-r from-orange-500 to-amber-600 text-white shadow-lg transform hover:scale-105" id="home-tab2" data-bs-toggle="tab" type="button" role="tab" aria-selected="true">
                            <i class="fa fa-user-plus mr-1 sm:mr-2"></i><span class="hidden sm:inline">Registrations</span><span class="sm:hidden">Register</span>
                        </a>
                    </li>
                    <li class="flex-1" role="presentation">
                        <a href="#profile-tab2-accounts" class="nav-link block w-full text-center py-3 sm:py-4 px-4 sm:px-6 text-xs sm:text-sm font-semibold rounded-xl transition-all duration-300 text-gray-700 bg-white hover:bg-gray-50 hover:shadow-md transform hover:scale-105" id="profile-tab2" data-bs-toggle="tab" type="button" role="tab" aria-selected="false">
                            <i class="fa fa-users mr-1 sm:mr-2"></i><span class="hidden sm:inline">Registered Accounts</span><span class="sm:hidden">Accounts</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

			<div class="tab-content">
				<div id="home-tab2-reg" class="tab-pane active animate-bounce-in">
					<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
						<div class="card-hover bg-white rounded-xl sm:rounded-2xl shadow-xl border-0 p-4 sm:p-6 lg:p-8 flex flex-col">
							<div class="text-center mb-4 sm:mb-6">
								<div class="mb-3 sm:mb-4">
									<div id="reader" class="mx-auto rounded-lg sm:rounded-xl overflow-hidden shadow-lg max-w-full"></div>
								</div>
							</div>
							
							<div class="mb-4 sm:mb-6">
								<div class="relative">
									<input type="text" name="QRCode" id="barcode_search" placeholder="Enter ID Code" autofocus 
										class="w-full px-4 sm:px-6 py-5 sm:py-6 text-lg sm:text-xl lg:text-2xl text-center bg-gradient-to-r from-gray-50 to-orange-50 border-2 border-gray-200 rounded-lg sm:rounded-xl focus:outline-none focus:ring-4 focus:ring-orange-200 focus:border-orange-400 transition-all duration-300 shadow-sm leading-relaxed font-semibold">
									<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
										<i class="fa fa-qrcode text-orange-400"></i>
									</div>
								</div>
							</div>
							
							<div class="grid grid-cols-2 gap-2 sm:gap-3 mt-auto">
								<button onClick="startScanner()" class="btn-primary inline-flex items-center justify-center px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-semibold text-white rounded-lg sm:rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300">
									<i class="fa fa-refresh mr-1 sm:mr-2"></i> <span class="hidden sm:inline">RESET</span><span class="sm:hidden">R</span>
								</button>
								<button class="btn-primary inline-flex items-center justify-center px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-semibold text-white rounded-lg sm:rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300" onClick="take_snapshot()">
									<i class="fa fa-camera mr-1 sm:mr-2"></i> <span class="hidden sm:inline">Take Photo</span><span class="sm:hidden">Photo</span>
								</button>
								<button class="btn-primary inline-flex items-center justify-center px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-semibold text-white rounded-lg sm:rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300" onClick="retake_snapshot()">
									<i class="fa fa-refresh mr-1 sm:mr-2"></i> <span class="hidden sm:inline">Retake</span><span class="sm:hidden">R</span>
								</button>
								<button class="bg-gradient-to-r from-green-500 to-emerald-600 inline-flex items-center justify-center px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-semibold text-white rounded-lg sm:rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300" id="register_button">
									<i class="fa fa-save mr-1 sm:mr-2"></i> <span class="hidden sm:inline">REGISTER</span><span class="sm:hidden">Save</span>
								</button>
							</div>
						</div>
						
						<div class="card-hover bg-white rounded-xl sm:rounded-2xl shadow-xl border-0 p-4 sm:p-6 lg:p-8 flex flex-col">
							<div class="text-center mb-3 sm:mb-4">
								<h3 class="text-lg sm:text-xl font-semibold text-gray-800 mb-1 sm:mb-2">Student Photo</h3>
								<p class="text-gray-500 text-xs sm:text-sm">Capture student's photo for registration</p>
							</div>
							<div id="my_camera" class="mx-auto rounded-lg sm:rounded-xl overflow-hidden shadow-lg max-w-full"></div>
							<input type="hidden" name="image" id="user_picture" class="image-tag">
							
							<!-- Scan ID Section moved here -->
							<div class="text-center mt-6 sm:mt-8 flex-grow flex flex-col justify-center">
								<div class="min-h-[60px] sm:min-h-[80px] lg:min-h-[100px] flex items-center justify-center">
									<h1 id="scan_result" class="text-xl sm:text-2xl lg:text-3xl font-bold bg-gradient-to-r from-orange-600 to-amber-600 bg-clip-text text-transparent leading-relaxed py-2">Scan ID</h1>
								</div>
								<p class="text-gray-500 text-xs sm:text-sm">Scan QR code or enter ID manually</p>
							</div>
						</div>
					</div>
					<p id="demo"></p>
				</div>

				<div id="profile-tab2-accounts" class="tab-pane hidden animate-fade-in">
					<div class="card-hover bg-white rounded-xl sm:rounded-2xl shadow-xl border-0 p-4 sm:p-6 lg:p-8 mb-6 sm:mb-8">
						<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 sm:mb-6">
							<div class="mb-3 sm:mb-0">
								<h2 class="text-xl sm:text-2xl font-bold bg-gradient-to-r from-orange-600 to-amber-600 bg-clip-text text-transparent">Recent Registrations</h2>
								<p class="text-gray-500 mt-1 text-sm">Latest student registrations</p>
							</div>
							<div class="flex items-center space-x-2">
								<div class="w-3 h-3 bg-green-500 rounded-full animate-pulse" id="liveIndicator"></div>
								<span class="text-sm text-gray-500">Live</span>
								<button id="refreshRecentBtn" class="ml-2 p-1 text-blue-500 hover:text-blue-700 transition-colors duration-200" title="Refresh Recent Registrations">
									<i class="fa fa-refresh"></i>
								</button>
							</div>
						</div>
						<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6" id="recentRegistrationsContainer">
							<!-- Loading Animation -->
							<div id="loadingAnimation" class="col-span-full flex flex-col items-center justify-center py-8">
								<div class="relative">
									<div class="w-16 h-16 border-4 border-orange-200 border-t-orange-500 rounded-full animate-spin"></div>
									<div class="absolute inset-0 flex items-center justify-center">
										<i class="fa fa-users text-orange-500 text-xl"></i>
									</div>
								</div>
								<p class="text-gray-500 mt-4 text-sm font-medium">Loading recent registrations...</p>
							</div>
							
							<!-- Error State -->
							<div id="errorState" class="col-span-full hidden flex flex-col items-center justify-center py-8">
								<div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
									<i class="fa fa-exclamation-triangle text-red-500 text-xl"></i>
								</div>
								<p class="text-gray-500 text-sm font-medium mb-2">Failed to load registrations</p>
								<button id="retryLoadBtn" class="text-blue-500 hover:text-blue-700 text-sm font-medium">
									<i class="fa fa-refresh mr-1"></i>Retry
								</button>
							</div>
							
							<!-- Empty State -->
							<div id="emptyState" class="col-span-full hidden flex flex-col items-center justify-center py-8">
								<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
									<i class="fa fa-users text-gray-400 text-xl"></i>
								</div>
								<p class="text-gray-500 text-sm font-medium">No recent registrations found</p>
								<p class="text-gray-400 text-xs mt-1">Register a new student to see them here</p>
							</div>
					    	
					    	<!-- PHP Generated Content (will be replaced by AJAX) -->
					    	<div id="phpContent" class="hidden">
					    	<?php
					    	$sql_recent_scan = mysqli_query($conn, "SELECT * FROM seens_student ORDER BY ss_date_added DESC LIMIT 4");
					    	$has_data = false;
					    	while($row_recent = mysqli_fetch_assoc($sql_recent_scan)){
					    		$has_data = true;
					    		echo '
					    			<div class="card-hover bg-gradient-to-br from-orange-50 to-amber-50 rounded-lg sm:rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 border border-orange-100 hover:border-orange-200 transform hover:scale-105">
							        	<img src="'.$row_recent['ss_photo_location'].'" class="w-full h-24 sm:h-32 object-cover transform scale-x-[-1]" alt="Student Photo">
										  <div class="p-3 sm:p-4">
										    <p class="text-xs sm:text-sm font-semibold text-gray-800" id="idNumber">'.$row_recent['ss_id_no'].'</p>
										    <p class="text-xs text-gray-500 mt-1">Registered</p>
										  </div>
										</div>
					    		';
					    	}
					    	?>
					    	</div>
						</div>
					</div>
					<div class="card-hover bg-white rounded-xl sm:rounded-2xl shadow-xl border-0 p-4 sm:p-6 lg:p-8">
						<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6 space-y-3 sm:space-y-0">
                            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                                <a href="bigdump/bigdump.php" class="btn-primary inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-semibold text-white rounded-lg sm:rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300">
                                    <i class="fa fa-database mr-1 sm:mr-2"></i> <span class="hidden sm:inline">Import SQL</span><span class="sm:hidden">Import</span>
                                </a>
                                <a href="sync_interface.php" class="btn-primary inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-semibold text-white rounded-lg sm:rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300">
                                    <i class="fa fa-sync-alt mr-1 sm:mr-2"></i> <span class="hidden sm:inline">Database Sync</span><span class="sm:hidden">Sync</span>
                                </a>
                            </div>
							<button class="btn-primary inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-semibold text-white rounded-lg sm:rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300" type="button" id="viewAllBtn">
								<i class="fa fa-arrow-down mr-1 sm:mr-2"></i> <span class="hidden sm:inline">View All</span><span class="sm:hidden">View</span>
							</button>
						</div>
						<div class="hidden transition-all duration-500 ease-in-out transform" id="collapseExample">
							<!-- Loading State for Table -->
							<div id="tableLoadingState" class="flex flex-col items-center justify-center py-12 mb-6">
								<div class="relative">
									<div class="w-16 h-16 border-4 border-blue-200 border-t-blue-500 rounded-full animate-spin"></div>
									<div class="absolute inset-0 flex items-center justify-center">
										<i class="fa fa-table text-blue-500 text-xl"></i>
									</div>
								</div>
								<p class="text-gray-500 mt-4 text-sm font-medium">Loading student data...</p>
							</div>
							
							<!-- Search Section -->
							<div class="mb-6 p-4 bg-gradient-to-r from-orange-50 to-amber-50 rounded-lg sm:rounded-xl border border-orange-100">
								<form id="searchForm" method="GET" class="flex flex-col sm:flex-row gap-4 items-center">
									<input type="hidden" name="tab" value="viewAll">
									<div class="flex-1 relative">
										<input type="text" name="search" id="searchStudent" placeholder="Search by ID number..." 
											value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
											class="w-full px-4 py-3 text-sm sm:text-base bg-white border-2 border-orange-200 rounded-lg sm:rounded-xl focus:outline-none focus:ring-4 focus:ring-orange-200 focus:border-orange-400 transition-all duration-300 shadow-sm">
										<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
											<i class="fa fa-search text-orange-400"></i>
										</div>
									</div>
									<div class="flex gap-2">
										<button type="submit" id="searchBtn" class="px-4 py-3 text-sm font-semibold text-white bg-gradient-to-r from-orange-500 to-amber-600 rounded-lg hover:shadow-lg transform hover:scale-105 transition-all duration-300">
											<i class="fa fa-search mr-2"></i>Search
										</button>
										<a href="?tab=viewAll" id="clearSearch" class="px-4 py-3 text-sm font-semibold text-gray-600 bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200 transition-all duration-300">
											<i class="fa fa-times mr-2"></i>Clear
										</a>
										<button type="button" id="exportData" class="px-4 py-3 text-sm font-semibold text-white bg-gradient-to-r from-green-500 to-emerald-600 rounded-lg hover:shadow-lg transform hover:scale-105 transition-all duration-300">
											<i class="fa fa-download mr-2"></i>Export
										</button>
									</div>
								</form>
								<div class="mt-3 text-sm text-gray-600">
									<span id="searchResults">
										<?php 
										if (isset($_GET['search']) && !empty($_GET['search'])) {
											echo 'Search results for: "' . htmlspecialchars($_GET['search']) . '"';
										} else {
											echo 'Showing all students';
										}
										?>
									</span>
								</div>
							</div>
							
							<div class="overflow-hidden rounded-xl shadow-lg border border-gray-200">
								<div class="overflow-x-auto">
																	<table class="min-w-full divide-y divide-gray-200">
									<thead class="bg-gradient-to-r from-orange-600 to-amber-600">
											<tr>
												<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
													<div class="flex items-center">
														<i class="fa fa-id-card mr-2"></i>
														ID Number
													</div>
												</th>
												<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
													<div class="flex items-center">
														<i class="fa fa-camera mr-2"></i>
														Photo
													</div>
												</th>
												<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
													<div class="flex items-center">
														<i class="fa fa-cogs mr-2"></i>
														Actions
													</div>
												</th>
											</tr>
										</thead>
										<tbody class="bg-white divide-y divide-gray-200" id="studentTableBody">
											<?php
												// Search and pagination setup
												$items_per_page = 10;
												$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
												$search = isset($_GET['search']) ? trim($_GET['search']) : '';
												$offset = ($page - 1) * $items_per_page;
												
												// Build search query
												$where_clause = '';
												$search_params = array();
												
												if (!empty($search)) {
													$where_clause = "WHERE seens_id_no LIKE ?";
													$search_params[] = "%$search%";
												}
												
												// Get total count with search
												$count_query = "SELECT COUNT(*) as total FROM seens_student $where_clause";
												if (!empty($search_params)) {
													$stmt = mysqli_prepare($conn, $count_query);
													mysqli_stmt_bind_param($stmt, 's', $search_params[0]);
													mysqli_stmt_execute($stmt);
													$total_result = mysqli_stmt_get_result($stmt);
												} else {
													$total_result = mysqli_query($conn, $count_query);
												}
												$total_items = mysqli_fetch_assoc($total_result)['total'];
												$total_pages = ceil($total_items / $items_per_page);
												
												// Get paginated and searched results
												$data_query = "SELECT * FROM seens_student $where_clause ORDER BY ss_date_added DESC LIMIT $items_per_page OFFSET $offset";
												if (!empty($search_params)) {
													$stmt = mysqli_prepare($conn, $data_query);
													mysqli_stmt_bind_param($stmt, 's', $search_params[0]);
													mysqli_stmt_execute($stmt);
													$sql_get_students = mysqli_stmt_get_result($stmt);
												} else {
													$sql_get_students = mysqli_query($conn, $data_query);
												}
												
												$num = 0;
												while($row_student = mysqli_fetch_assoc($sql_get_students)){
													$num += 1;
													echo '
													<tr class="hover:bg-gradient-to-r hover:from-orange-50 hover:to-amber-50 transition-all duration-300 group">
														<td class="px-4 py-2 whitespace-nowrap">
															<div class="flex items-center">
																<div class="flex-shrink-0">
																	<div class="w-2 h-2 bg-green-500 rounded-full"></div>
																</div>
																<div class="ml-3">
																	<div class="text-sm font-semibold text-gray-900">'.$row_student['ss_id_no'].'</div>
																</div>
															</div>
														</td>
														<td class="px-4 py-2 whitespace-nowrap">
															<div class="flex items-center">
																<div class="flex-shrink-0 h-8 w-8 relative">
																	<img class="h-8 w-8 rounded-full object-cover transform scale-x-[-1] shadow-sm group-hover:shadow-md transition-all duration-300" src="'.$row_student['ss_photo_location'].'" alt="Student Photo">
																	<div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-blue-500 rounded-full border border-white flex items-center justify-center">
																		<i class="fa fa-check text-white text-xs"></i>
																	</div>
																</div>
															</div>
														</td>
														<td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
															<div class="flex space-x-1">
																<button class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-semibold rounded text-white bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-red-500 transition-all duration-300 transform hover:scale-105" id="deleteStudent">
																	<i class="fa fa-trash mr-1"></i>
																	Delete
																</button>
																<button class="view-student-btn inline-flex items-center px-2 py-1 border border-gray-300 text-xs font-semibold rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-blue-500 transition-all duration-300 transform hover:scale-105" data-id="'.$row_student['ss_id_no'].'" data-photo="'.$row_student['ss_photo_location'].'">
																	<i class="fa fa-eye mr-1"></i>
																	View
																</button>
															</div>
														</td>
													</tr>
													';
												}
											?>
										</tbody>
									</table>
								</div>
								
								<!-- Pagination Controls -->
								<?php if ($total_pages > 1): ?>
								<div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
									<div class="flex-1 flex justify-between sm:hidden">
										<?php if ($page > 1): ?>
										<a href="?page=<?php echo $page - 1; ?>&tab=viewAll<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
											Previous
										</a>
										<?php endif; ?>
										<?php if ($page < $total_pages): ?>
										<a href="?page=<?php echo $page + 1; ?>&tab=viewAll<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
											Next
										</a>
										<?php endif; ?>
									</div>
									<div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
										<div>
											<p class="text-sm text-gray-700">
												Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $items_per_page, $total_items); ?></span> of <span class="font-medium"><?php echo $total_items; ?></span> results
											</p>
										</div>
										<div>
											<nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
												<?php if ($page > 1): ?>
												<a href="?page=<?php echo $page - 1; ?>&tab=viewAll<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
													<span class="sr-only">Previous</span>
													<i class="fa fa-chevron-left"></i>
												</a>
												<?php endif; ?>
												
												<?php
												$start_page = max(1, $page - 2);
												$end_page = min($total_pages, $page + 2);
												
												for ($i = $start_page; $i <= $end_page; $i++):
												?>
												<a href="?page=<?php echo $i; ?>&tab=viewAll<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-orange-50 border-orange-500 text-orange-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
													<?php echo $i; ?>
												</a>
												<?php endfor; ?>
												
												<?php if ($page < $total_pages): ?>
												<a href="?page=<?php echo $page + 1; ?>&tab=viewAll<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
													<span class="sr-only">Next</span>
													<i class="fa fa-chevron-right"></i>
												</a>
												<?php endif; ?>
											</nav>
										</div>
									</div>
								</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Student View Modal -->
				<div id="studentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
					<div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
						<div class="mt-3">
							<!-- Modal Header -->
							<div class="flex items-center justify-between mb-4">
								<h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Student Details</h3>
								<button id="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
									<i class="fa fa-times text-xl"></i>
								</button>
							</div>
							
							<!-- Modal Content -->
							<div class="flex flex-col md:flex-row gap-6">
								<!-- Student Photo -->
								<div class="flex-1">
									<div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-4 border border-blue-100">
										<h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
											<i class="fa fa-camera mr-2 text-blue-500"></i>
											Student Photo
										</h4>
										<div class="relative">
											<img id="modalStudentPhoto" src="" alt="Student Photo" class="w-full h-64 md:h-80 object-cover rounded-lg shadow-lg transform scale-x-[-1]">
											<div class="absolute top-2 right-2 bg-blue-500 text-white px-2 py-1 rounded-full text-xs font-semibold">
												<i class="fa fa-check mr-1"></i>Verified
											</div>
										</div>
									</div>
								</div>
								
								<!-- Student Information -->
								<div class="flex-1">
									<div class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl p-4 border border-gray-200">
										<h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
											<i class="fa fa-user mr-2 text-blue-500"></i>
											Student Information
										</h4>
										<div class="space-y-3">
											<div class="flex items-center justify-between">
												<span class="text-sm text-gray-600">ID Number:</span>
												<span id="modalStudentId" class="text-sm font-semibold text-gray-900"></span>
											</div>
											<div class="flex items-center justify-between">
												<span class="text-sm text-gray-600">Status:</span>
												<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
													<i class="fa fa-check-circle mr-1"></i>
													Registered
												</span>
											</div>
											<div class="flex items-center justify-between">
												<span class="text-sm text-gray-600">Registration Date:</span>
												<span class="text-sm text-gray-900"><?php echo date('M d, Y'); ?></span>
											</div>
										</div>
										
										<!-- Action Buttons -->
										<div class="mt-6 space-y-2">
											<button id="printIdCard" class="w-full bg-gradient-to-r from-orange-500 to-amber-600 text-white py-2 px-4 rounded-lg hover:shadow-lg transform hover:scale-105 transition-all duration-300 font-semibold">
												<i class="fa fa-print mr-2"></i>
												Print ID Card
											</button>
											<button class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-200 transition-all duration-300 font-semibold">
												<i class="fa fa-download mr-2"></i>
												Download Photo
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			
		</div>

		<!-- Minimal RFID System Status (Bottom) -->
		<div class="mt-8 sm:mt-12 mb-4">
			<div class="bg-white/80 backdrop-blur-sm rounded-lg border border-gray-200 p-3">
				<div class="flex items-center justify-between">
					<div class="flex items-center space-x-3">
						<div id="rfid-status-container" class="flex items-center space-x-4">
							<!-- Status will be populated by JavaScript -->
						</div>
					</div>
					<div class="flex items-center space-x-2">
						<a href="rfid-writer/new_interface.php" target="_blank" class="text-xs bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded transition-colors duration-200">
							<i class="fa fa-external-link mr-1"></i> RFID Writer
						</a>
						<button onclick="refreshRFIDStatus()" class="text-xs bg-orange-500 hover:bg-orange-600 text-white px-2 py-1 rounded transition-colors duration-200">
							<i class="fa fa-refresh mr-1"></i> Refresh
						</button>
					</div>
				</div>
			</div>
		</div>
		
	</div>
	<footer class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-6 sm:py-8 px-4 mt-8 sm:mt-12">
		<div class="max-w-7xl mx-auto">
			<div class="flex flex-col md:flex-row justify-center items-center space-y-3 md:space-y-0 md:space-x-6 lg:space-x-8">
				<div class="flex items-center space-x-2 sm:space-x-3">
					<img src="cict.jpg" class="w-6 h-6 sm:w-8 sm:h-8 rounded-full shadow-lg" alt="CICT Logo">
					<span class="text-xs sm:text-sm font-medium text-center">College of Information and Communication Technology</span>
				</div>
				<div class="text-gray-400 hidden md:block">|</div>
				<div class="flex items-center space-x-2 sm:space-x-3">
					<span class="text-xs sm:text-sm font-medium text-center">Safety and Security Office</span>
					<img src="sso.jpg" class="w-6 h-6 sm:w-8 sm:h-8 rounded-full shadow-lg" alt="SSO Logo">
				</div>
			</div>
			<div class="text-center mt-3 sm:mt-4">
				<p class="text-xs text-gray-400">© 2024 SEENS - Student Entry and Exit Notification System</p>
			</div>
		</div>
	</footer>

</body>
<script>
	// Tab Switching Functionality
	document.addEventListener('DOMContentLoaded', function() {
		// Disable automatic scroll restoration for better control
		if ('scrollRestoration' in history) {
			history.scrollRestoration = 'manual';
		}
		const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
		const tabPanes = document.querySelectorAll('.tab-pane');
		
		// Function to switch to a specific tab
		function switchToTab(tabId) {
			            // Remove active class from all tabs and panes
            tabLinks.forEach(l => {
                l.classList.remove('active');
                l.classList.remove('bg-gradient-to-r', 'from-orange-500', 'to-amber-600', 'text-white', 'shadow-lg');
                l.classList.add('text-gray-700', 'bg-white');
            });
			
			tabPanes.forEach(pane => {
				pane.classList.remove('active');
				pane.classList.add('hidden');
			});
			
			            // Find and activate the target tab
            const targetTab = document.querySelector(`[href="#${tabId}"]`);
            if (targetTab) {
                targetTab.classList.add('active');
                targetTab.classList.add('bg-gradient-to-r', 'from-orange-500', 'to-amber-600', 'text-white', 'shadow-lg');
                targetTab.classList.remove('text-gray-700', 'bg-white');
            }
			
			// Show corresponding pane
			const targetPane = document.getElementById(tabId);
			if (targetPane) {
				targetPane.classList.add('active');
				targetPane.classList.remove('hidden');
			}
			
			// Handle tab-specific actions
			if (tabId === 'home-tab2-reg') {
				setTimeout(() => {
					document.getElementById('barcode_search').focus();
					// Start QR scanner when switching to registration tab
					startScanner();
				}, 200);
			} else if (tabId === 'profile-tab2-accounts') {
				// Stop QR scanner when switching to accounts tab
				setTimeout(() => {
					stopScanner();
				}, 100);
			}
		}
		
			// Check URL parameters on page load
	const urlParams = new URLSearchParams(window.location.search);
	const tabParam = urlParams.get('tab');
	
	// Restore scroll position if coming from pagination
	if (tabParam === 'viewAll' && urlParams.get('page')) {
		// Prevent default scroll to top behavior
		history.scrollRestoration = 'manual';
		// Store current scroll position for restoration
		sessionStorage.setItem('scrollPosition', 'table');
	}
		
		if (tabParam === 'viewAll') {
			// Switch to Registered Accounts tab and show the table
			switchToTab('profile-tab2-accounts');
			
			// Show the table if it exists
			const collapseExample = document.getElementById('collapseExample');
			if (collapseExample) {
				collapseExample.classList.remove('hidden');
				// Show loading state initially
				$('#tableLoadingState').removeClass('hidden');
				$('.overflow-hidden.rounded-xl.shadow-lg.border.border-gray-200').addClass('hidden');
				
				// Hide loading after a short delay
				setTimeout(() => {
					$('#tableLoadingState').addClass('hidden');
					$('.overflow-hidden.rounded-xl.shadow-lg.border.border-gray-200').removeClass('hidden');
				}, 800);
			}
			
			// Update the View All button text
			const viewAllBtn = document.getElementById('viewAllBtn');
			if (viewAllBtn) {
				viewAllBtn.innerHTML = '<i class="fa fa-arrow-up mr-1"></i> Hide All';
			}
			
			// Scroll to the table section smoothly
			setTimeout(() => {
				const tableSection = document.getElementById('collapseExample');
				if (tableSection) {
					// Check if we should scroll to table (from pagination)
					const shouldScrollToTable = sessionStorage.getItem('scrollPosition') === 'table';
					
					if (shouldScrollToTable) {
						// Get the table's position and scroll to it
						const tableRect = tableSection.getBoundingClientRect();
						const scrollTop = window.pageYOffset + tableRect.top - 100; // 100px offset from top
						
						window.scrollTo({
							top: scrollTop,
							behavior: 'smooth'
						});
						
						// Clear the stored position
						sessionStorage.removeItem('scrollPosition');
					}
				}
			}, 300);
		}
		
		tabLinks.forEach(link => {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				
				const targetId = this.getAttribute('href').substring(1);
				switchToTab(targetId);
			});
		});
		
		// View All Button Functionality
		const viewAllBtn = document.getElementById('viewAllBtn');
		const collapseExample = document.getElementById('collapseExample');
		
		if (viewAllBtn && collapseExample) {
			viewAllBtn.addEventListener('click', function(e) {
				e.preventDefault();
				
				const isHidden = collapseExample.classList.contains('hidden');
				
				if (isHidden) {
					// Show loading state first
					collapseExample.classList.remove('hidden');
					$('#tableLoadingState').removeClass('hidden');
					$('.overflow-hidden.rounded-xl.shadow-lg.border.border-gray-200').addClass('hidden');
					
					this.innerHTML = '<i class="fa fa-arrow-up mr-1"></i> Hide All';
					
					// Load users data via AJAX
					$.ajax({
						type: "GET",
						url: "backend_scripts/get_users.php",
						dataType: 'json',
						success: function(response) {
							if (response.success == 1) {
								// Populate the table with user data
								populateUsersTable(response.data);
								
								// Hide loading and show table
								setTimeout(() => {
									$('#tableLoadingState').addClass('hidden');
									$('.overflow-hidden.rounded-xl.shadow-lg.border.border-gray-200').removeClass('hidden');
								}, 500);
							} else {
								// Show error message
								$('#tableLoadingState').addClass('hidden');
								$('.overflow-hidden.rounded-xl.shadow-lg.border.border-gray-200').removeClass('hidden');
								Swal.fire('Error', 'Failed to load users data', 'error');
							}
						},
						error: function() {
							$('#tableLoadingState').addClass('hidden');
							$('.overflow-hidden.rounded-xl.shadow-lg.border.border-gray-200').removeClass('hidden');
							Swal.fire('Error', 'Failed to load users data', 'error');
						}
					});
				} else {
					// Hide the table
					collapseExample.classList.add('hidden');
					this.innerHTML = '<i class="fa fa-arrow-down mr-1"></i> View All';
				}
			});
		}
	});

	function focusGain(){
		$("#barcode_search").focus();
		$("#barcode_search").val('');
		document.getElementById("scan_result").innerHTML = "Scan ID";

	}

	// Function to populate users table
	function populateUsersTable(users) {
		const tableBody = document.querySelector('#studentTableBody');
		if (!tableBody) return;
		
		// Clear existing table content
		tableBody.innerHTML = '';
		
		if (users.length === 0) {
			tableBody.innerHTML = '<tr><td colspan="3" class="px-4 py-4 text-center text-gray-500">No users found</td></tr>';
			return;
		}
		
		users.forEach((user, index) => {
			const row = document.createElement('tr');
			row.className = 'hover:bg-gradient-to-r hover:from-orange-50 hover:to-amber-50 transition-all duration-300 group';
			
			const idCell = document.createElement('td');
			idCell.className = 'px-4 py-2 whitespace-nowrap';
			idCell.innerHTML = `
				<div class="flex items-center">
					<div class="flex-shrink-0">
						<div class="w-2 h-2 bg-green-500 rounded-full"></div>
					</div>
					<div class="ml-3">
						<div class="text-sm font-semibold text-gray-900">${user.id_no}</div>
					</div>
				</div>
			`;
			
			const photoCell = document.createElement('td');
			photoCell.className = 'px-4 py-2 whitespace-nowrap';
			photoCell.innerHTML = `
				<div class="flex items-center">
					<div class="flex-shrink-0 h-8 w-8 relative">
						<img class="h-8 w-8 rounded-full object-cover transform scale-x-[-1] shadow-sm group-hover:shadow-md transition-all duration-300" src="${user.photo_location}" alt="Student Photo" onerror="this.src='assets/images/default-avatar.png'">
						<div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-blue-500 rounded-full border border-white flex items-center justify-center">
							<i class="fa fa-check text-white text-xs"></i>
						</div>
					</div>
				</div>
			`;
			
			const actionsCell = document.createElement('td');
			actionsCell.className = 'px-4 py-2 whitespace-nowrap text-sm font-medium';
			actionsCell.innerHTML = `
				<div class="flex space-x-1">
					<button onclick="deleteUser('${user.id_no}')" class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-semibold rounded text-white bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-red-500 transition-all duration-300 transform hover:scale-105">
						<i class="fa fa-trash mr-1"></i>
						Delete
					</button>
					<button onclick="printIdCard('${user.id_no}')" class="view-student-btn inline-flex items-center px-2 py-1 border border-gray-300 text-xs font-semibold rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-blue-500 transition-all duration-300 transform hover:scale-105" data-id="${user.id_no}" data-photo="${user.photo_location}">
						<i class="fa fa-print mr-1"></i>
						Print ID
					</button>
					<button onclick="writeRfidCard('${user.id_no}')" class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-semibold rounded text-white bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-purple-500 transition-all duration-300 transform hover:scale-105">
						<i class="fa fa-credit-card mr-1"></i>
						Write RFID
					</button>
				</div>
			`;
			
			row.appendChild(idCell);
			row.appendChild(photoCell);
			row.appendChild(actionsCell);
			tableBody.appendChild(row);
		});
	}

	// Listen for registration success to refresh users table
	$(document).on('registrationSuccess', function() {
		// If the users table is currently visible, refresh it
		const collapseExample = document.getElementById('collapseExample');
		if (collapseExample && !collapseExample.classList.contains('hidden')) {
			// Refresh the users table
			$.ajax({
				type: "GET",
				url: "backend_scripts/get_users.php",
				dataType: 'json',
				success: function(response) {
					if (response.success == 1) {
						populateUsersTable(response.data);
					}
				}
			});
		}
	});

	// Global delete user function
	function deleteUser(userId) {
		Swal.fire({
			title: "Delete Registration",
			text: "Are you sure you want to delete the registration with ID Number " + userId + "?",
			icon: "warning",
			showCancelButton: true,
			confirmButtonColor: "#d33",
			cancelButtonColor: "#3085d6",
			confirmButtonText: "Yes, delete it!",
			cancelButtonText: "Cancel"
		}).then((result) => {
			if (result.isConfirmed) {
				$.ajax({
					url: 'backend_scripts/delete_user.php',
					method: 'POST',
					data: { 'token': 'Seait123', 'qr': userId },
					dataType: 'json',
					success: function(response) {
						if (response.success == 1) {
							Swal.fire({
								title: "Deleted!",
								text: "Registration has been deleted successfully.",
								icon: "success",
								timer: 2000,
								showConfirmButton: false
							}).then(() => {
								// Refresh the users table
								$.ajax({
									type: "GET",
									url: "backend_scripts/get_users.php",
									dataType: 'json',
									success: function(response) {
										if (response.success == 1) {
											populateUsersTable(response.data);
										}
									}
								});
							});
						} else {
							Swal.fire({
								title: "Error!",
								text: "There was an error deleting the registration.",
								icon: "error"
							});
						}
					},
					error: function(xhr, status, error) {
						Swal.fire({
							title: "Error!",
							text: "There was an error deleting the registration.",
							icon: "error"
						});
					}
				});
			}
		});
	}

	// Update scan result when manually typing
	$("#barcode_search").on("input", function() {
		var idQR = $(this).val();
		if (idQR.trim() !== '') {
			document.getElementById("scan_result").innerHTML = idQR;
		} else {
			document.getElementById("scan_result").innerHTML = "Scan ID";
		}
	});

	window.addEventListener("keydown", function (event) {
	  if (event.key == 'Enter') {
	    
	    var idQR = $("#barcode_search").val();
	    document.getElementById("scan_result").innerHTML = idQR;
		  playSound();
		  $("#barcode_search").val('');
		  $.ajax({
		    type: "POST",
		    url: "backend_scripts/check_id.php",
		    data: {'token': 'Seait123', 'qr': idQR},
		    dataType: 'json',
		    success: function(resCheck) {
		      console.log(resCheck);
		      var stats = resCheck.message;
		      if(stats != '0'){
		      	document.getElementById("scan_result").innerHTML = "Scan ID";
		      	// Swal.fire('Student is already registered!');

		      	Swal.fire({
				  title: "<strong>Account already registered.</strong>",
				  icon: "error",
				  html: `
				    <img id="captured-image-check" style="width:300px; transform: scaleX(-100%);" src="`+stats+`"/>
				  `,
				  showCloseButton: false,
				  showCancelButton: false,
				  focusConfirm: false,
				});

		      	$("#barcode_search").val('');

		      }
		       
		       
		    }
		  });
		  var qr = document.getElementById('scan_result').innerHTML;
		  //alert(qr);

	  } else if (event.which == 13) {
	    // alert('Enter in which');
	  }
	});

	// QR code reader instance and settings
	let qrReader = null;
	const qrConstraints = {
	  facingMode: "environment"
	};
	const qrConfig = {
	  fps: 10,
	  qrbox: {
	    width: 300,
	    height: 300
	  }
	};
	
	// Initialize QR reader
	function initializeQRReader() {
		if (!qrReader) {
			const readerElement = document.getElementById("reader");
			if (readerElement) {
				qrReader = new Html5Qrcode("reader");
			}
		}
	}
	const qrOnSuccess = (decodedText, decodedResult) => {
	  //stopScanner(); // Stop the scanner
	  console.log(`Message: ${decodedText}, Result: ${JSON.stringify(decodedResult)}`);
	  $("#barcode_search").val(decodedText); // Set the value of the barcode field
	  document.getElementById("scan_result").innerHTML = decodedText;
	  playSound();
	  $.ajax({
	    type: "POST",
	    url: "backend_scripts/check_id.php",
	    data: {token: 'Seait123', qr: decodedText},
	    dataType: 'json',
	    success: function(resCheck) {
	      console.log(resCheck);
	      var stats = resCheck.message;
	      if(stats != '0'){
	      	document.getElementById("scan_result").innerHTML = "Scan ID";
	      	Swal.fire({
				  title: "<strong>Account already registered.</strong>",
				  icon: "error",
				  html: `
				    <img id="captured-image-check" style="width:300px; transform: scaleX(-100%);" src="`+stats+`"/>
				  `,
				  showCloseButton: false,
				  showCancelButton: false,
				  focusConfirm: false,
				});

		      	$("#barcode_search").val('');
		      	$("#barcode_search").focus();
	      }
	      else{
	      	
	      }
	       
	       
	    }
	  });
	};

	// Methods: start / stop
	const startScanner = () => {
	  // Initialize QR reader if not already done
	  initializeQRReader();
	  
	  if (!qrReader) {
	    console.error("QR Reader not initialized");
	    return;
	  }
	  
	  $("#barcode_search").val('');
	  document.getElementById("scan_result").innerHTML = "Scan ID";
	  $("#barcode_search").focus();
	  $("#reader").show();
	  $("#product_info").hide();
	  
	  // Check if scanner is already running
	  if (qrReader.isScanning) {
	    return;
	  }
	  
	  qrReader.start(
	    qrConstraints,
	    qrConfig,
	    qrOnSuccess,
	  ).catch(console.error);
	};

	const stopScanner = () => {
	  $("#reader").hide();
	  $("#product_info").show();
	  if (qrReader && qrReader.isScanning) {
	    qrReader.stop().catch(console.error);
	  }
	};

	// Start scanner on button click
	$(document).ready(function() {
	  // Initialize QR reader
	  initializeQRReader();
	  
	  // Start scanner only if we're on the registration tab
	  const urlParams = new URLSearchParams(window.location.search);
	  const tabParam = urlParams.get('tab');
	  
	  if (!tabParam || tabParam !== 'viewAll') {
	    // We're on the registration tab, start scanner
	    setTimeout(() => {
	      startScanner();
	    }, 500);
	  }
	});

	Webcam.set({
        width: 480,
        height: 360,
        image_format: 'jpeg',
        jpeg_quality: 100,
        flip_horiz: true
    });
  
    Webcam.attach('#my_camera' );
  
    function take_snapshot() {
        Webcam.snap( function(data_uri, canvas, context) {
		    var flip_canvas = document.createElement('canvas');
		    flip_canvas.width = canvas.width;
		    flip_canvas.height = canvas.height;

		    var flip_context = flip_canvas.getContext('2d');
		    flip_context.translate(canvas.width, 0);
		    flip_context.scale(-1, 1);
		    flip_context.drawImage(canvas, 0, 0);

		    data_uri = flip_canvas.toDataURL('image/' + Webcam.params.image_format, Webcam.params.jpeg_quality / 100 );
            $(".image-tag").val(data_uri);
            document.getElementById('my_camera').innerHTML = '<img id="captured-image" src="'+data_uri+'" style="width: 100%; height: 100%; object-fit: cover;"/>';
            //console.log(data_uri);
        } );
    }
    function retake_snapshot(){
    	Webcam.attach('#my_camera' );
    }
    // Submit 
	$("#register_button").on("click", function(evt) {
	  evt.preventDefault();
	  var QRCode = $("#barcode_search").val() || document.getElementById('scan_result').innerHTML;
	  var user_picture = $("#user_picture").val();

	  // Debug logging
	  console.log("QR Code:", QRCode);
	  console.log("User Picture length:", user_picture ? user_picture.length : 0);
	  console.log("Scan Result:", document.getElementById('scan_result').innerHTML);

	  $.ajax({
	    type: "POST",
	    url: "backend_scripts/add_user.php",
	    data: {'token': 'Seait123', 'qr': QRCode, 'picture': user_picture},
	    dataType: 'json',
	    success: function(res) {
	      console.log("Response:", res);
	      Swal.fire(res.message);
	      startScanner();
	      
	      // Trigger refresh of recent registrations
	      $(document).trigger('registrationSuccess');
	    },
	    error: function(xhr, status, error) {
	      console.error("AJAX Error:", status, error);
	      console.error("Response Text:", xhr.responseText);
	      Swal.fire("Error saving data: " + error);
	    }
	  });
	  	retake_snapshot();
	  	$("#barcode_search").val('');
	  	var input = $("#barcode_search");
		input[0].selectionStart = input[0].selectionEnd = input.val().length;
		$("#barcode_search").focus();

	});

	// Delete student functionality
	$(document).on('click', '#deleteStudent', function(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var row = $(this).closest('tr');
		var id = row.find("td:first-child .text-sm.font-semibold").text().trim();
		
		Swal.fire({
			title: "Delete Registration",
			text: "Are you sure you want to delete the registration with ID Number " + id + "?",
			icon: "warning",
			showCancelButton: true,
			confirmButtonColor: "#d33",
			cancelButtonColor: "#3085d6",
			confirmButtonText: "Yes, delete it!",
			cancelButtonText: "Cancel"
		}).then((result) => {
			if (result.isConfirmed) {
				// Show loading state
				$(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Deleting...');
				
				$.ajax({
					url: 'backend_scripts/delete_user.php',
					method: 'POST',
					data: { 'token': 'Seait123', 'qr': id },
					dataType: 'json',
					success: function(response) {
						if (response.success == 1) {
							Swal.fire({
								title: "Deleted!",
								text: "Registration has been deleted successfully.",
								icon: "success",
								timer: 2000,
								showConfirmButton: false
							}).then(() => {
								// Remove the row with animation
								row.fadeOut(300, function() {
									$(this).remove();
									// Refresh the page to update pagination
									window.location.reload();
								});
							});
						} else {
							Swal.fire({
								title: "Error!",
								text: "There was an error deleting the registration.",
								icon: "error"
							});
							// Reset button state
							$(this).prop('disabled', false).html('<i class="fa fa-trash mr-1"></i>Delete');
						}
					},
					error: function(xhr, status, error) {
						Swal.fire({
							title: "Error!",
							text: "There was an error deleting the registration.",
							icon: "error"
						});
						// Reset button state
						$(this).prop('disabled', false).html('<i class="fa fa-trash mr-1"></i>Delete');
					}
				});
			}
		});
	});


    // Custom Table Functionality
    $(document).ready(function() {
	    	// Search form submission
	$('#searchForm').on('submit', function(e) {
		var searchTerm = $('#searchStudent').val().trim();
		if (searchTerm === '') {
			e.preventDefault();
			window.location.href = '?tab=viewAll';
		}
	});
	
	// Enter key search
	$('#searchStudent').on('keypress', function(e) {
		if (e.which === 13) { // Enter key
			$('#searchForm').submit();
		}
	});
	
	// Auto-refresh recent registrations
	function refreshRecentRegistrations() {
		$.ajax({
			url: 'backend_scripts/get_recent_registrations.php',
			type: 'GET',
			dataType: 'json',
			timeout: 5000,
			success: function(response) {
				if (response.success && response.data) {
					var container = $('#recentRegistrationsContainer');
					
					// Hide loading and error states
					$('#loadingAnimation, #errorState, #emptyState').addClass('hidden');
					
					// Build new content
					var newContent = '';
					if (response.data.length > 0) {
						response.data.forEach(function(student) {
							newContent += `
								<div class="card-hover bg-gradient-to-br from-orange-50 to-amber-50 rounded-lg sm:rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 border border-orange-100 hover:border-orange-200 transform hover:scale-105">
									<img src="${student.photo_location}" class="w-full h-24 sm:h-32 object-cover transform scale-x-[-1]" alt="Student Photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
									<div class="w-full h-24 sm:h-32 bg-gray-100 rounded-lg flex items-center justify-center hidden">
										<i class="fa fa-user text-gray-400 text-2xl"></i>
									</div>
									<div class="p-3 sm:p-4">
										<p class="text-xs sm:text-sm font-semibold text-gray-800" id="idNumber">${student.id_no}</p>
										<p class="text-xs text-gray-500 mt-1">Registered</p>
									</div>
								</div>
							`;
						});
						
						// Update container with new content
						container.html(newContent);
					} else {
						// Show empty state
						$('#emptyState').removeClass('hidden');
					}
				} else {
					// Show error state
					$('#loadingAnimation').addClass('hidden');
					$('#errorState').removeClass('hidden');
				}
			},
			error: function(xhr, status, error) {
				console.error('Error refreshing recent registrations:', error);
				// Show error state
				$('#loadingAnimation').addClass('hidden');
				$('#errorState').removeClass('hidden');
			}
		});
	}
	
	// Initial load of recent registrations
	function loadRecentRegistrations() {
		// Show loading state
		$('#loadingAnimation').removeClass('hidden');
		$('#errorState, #emptyState').addClass('hidden');
		
		// Hide PHP content initially
		$('#phpContent').addClass('hidden');
		
		// Load data via AJAX with timeout
		var ajaxTimeout = setTimeout(function() {
			$('#loadingAnimation').addClass('hidden');
			$('#errorState').removeClass('hidden');
		}, 10000); // 10 second timeout
		
		$.ajax({
			url: 'backend_scripts/get_recent_registrations.php',
			type: 'GET',
			dataType: 'json',
			timeout: 10000,
			success: function(response) {
				clearTimeout(ajaxTimeout);
				if (response.success && response.data) {
					var container = $('#recentRegistrationsContainer');
					
					// Hide loading and error states
					$('#loadingAnimation, #errorState, #emptyState').addClass('hidden');
					
					// Build new content
					var newContent = '';
					if (response.data.length > 0) {
						response.data.forEach(function(student) {
							newContent += `
								<div class="card-hover bg-gradient-to-br from-orange-50 to-amber-50 rounded-lg sm:rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 border border-orange-100 hover:border-orange-200 transform hover:scale-105">
									<img src="${student.photo_location}" class="w-full h-24 sm:h-32 object-cover transform scale-x-[-1]" alt="Student Photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
									<div class="w-full h-24 sm:h-32 bg-gray-100 rounded-lg flex items-center justify-center hidden">
										<i class="fa fa-user text-gray-400 text-2xl"></i>
									</div>
									<div class="p-3 sm:p-4">
										<p class="text-xs sm:text-sm font-semibold text-gray-800" id="idNumber">${student.id_no}</p>
										<p class="text-xs text-gray-500 mt-1">Registered</p>
									</div>
								</div>
							`;
						});
						
						// Update container with new content
						container.html(newContent);
					} else {
						// Show empty state
						$('#emptyState').removeClass('hidden');
					}
				} else {
					// Show error state
					$('#loadingAnimation').addClass('hidden');
					$('#errorState').removeClass('hidden');
				}
			},
			error: function(xhr, status, error) {
				clearTimeout(ajaxTimeout);
				console.error('Error loading recent registrations:', error);
				// Show error state
				$('#loadingAnimation').addClass('hidden');
				$('#errorState').removeClass('hidden');
			}
		});
	}
	
	// Set up auto-refresh every 5 seconds
	setInterval(refreshRecentRegistrations, 5000);
	
	// Manual refresh button
	$('#refreshRecentBtn').on('click', function() {
		$(this).find('i').addClass('fa-spin');
		refreshRecentRegistrations();
		setTimeout(() => {
			$(this).find('i').removeClass('fa-spin');
		}, 1000);
	});
	
	// Retry button functionality
	$('#retryLoadBtn').on('click', function() {
		loadRecentRegistrations();
	});
	
	// Load initial data when page loads
	loadRecentRegistrations();
	
	// Also refresh after successful registration
	$(document).on('registrationSuccess', function() {
		refreshRecentRegistrations();
	});
	    
	    // Export functionality
	    $('#exportData').on('click', function() {
	        var searchTerm = $('#searchStudent').val();
	        var visibleRows = $('#studentTableBody tr:visible');
	        
	        if (visibleRows.length === 0) {
	            Swal.fire({
	                title: 'No Data to Export',
	                text: 'No students found to export.',
	                icon: 'info'
	            });
	            return;
	        }
	        
	        // Create CSV content
	        var csvContent = "data:text/csv;charset=utf-8,";
	        csvContent += "ID Number,Photo URL,Date Added\n";
	        
	        visibleRows.each(function() {
	            var idNumber = $(this).find('td:first-child .text-sm.font-semibold').text();
	            var photoUrl = $(this).find('td:nth-child(2) img').attr('src');
	            var dateAdded = new Date().toLocaleDateString();
	            csvContent += '"' + idNumber + '","' + photoUrl + '","' + dateAdded + '"\n';
	        });
	        
	        // Download CSV file
	        var encodedUri = encodeURI(csvContent);
	        var link = document.createElement("a");
	        link.setAttribute("href", encodedUri);
	        link.setAttribute("download", "students_data.csv");
	        document.body.appendChild(link);
	        link.click();
	        document.body.removeChild(link);
	        
	        Swal.fire({
	            title: 'Export Successful',
	            text: 'Student data has been exported to CSV file.',
	            icon: 'success'
	        });
	    });
	    
	    // Modal functionality
	    $(document).on('click', '.view-student-btn', function() {
	        var studentId = $(this).data('id');
	        var studentPhoto = $(this).data('photo');
	        
	        // Update modal content
	        $('#modalStudentId').text(studentId);
	        $('#modalStudentPhoto').attr('src', studentPhoto);
	        $('#modalTitle').text('Student Details - ' + studentId);
	        
	        // Show modal with animation
	        $('#studentModal').removeClass('hidden').addClass('animate-fade-in');
	    });
	    
	    // Close modal functionality
	    $('#closeModal').on('click', function() {
	        $('#studentModal').addClass('hidden');
	    });
	    
	    // Close modal when clicking outside
	    $('#studentModal').on('click', function(e) {
	        if (e.target === this) {
	            $(this).addClass('hidden');
	        }
	    });
	    
	    // Close modal with Escape key
	    $(document).on('keydown', function(e) {
	        if (e.key === 'Escape' && !$('#studentModal').hasClass('hidden')) {
	            $('#studentModal').addClass('hidden');
	        }
	    });
	    
	    // Print ID Card functionality
	    $('#printIdCard').on('click', function() {
	        var studentId = $('#modalStudentId').text();
	        var studentPhoto = $('#modalStudentPhoto').attr('src');
	        
	        // Create print window content
	        var printContent = `
	            <!DOCTYPE html>
	            <html>
	            <head>
	                <title>Student ID Card - ${studentId}</title>
	                <style>
	                    @media print {
	                        body { margin: 0; padding: 0; }
	                        .id-card { 
	                            width: 8cm; 
	                            height: 4cm; 
	                            border: 2px solid #000; 
	                            margin: 0.5cm; 
	                            padding: 0.3cm; 
	                            box-sizing: border-box;
	                            font-family: Arial, sans-serif;
	                            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
	                            color: white;
	                            position: relative;
	                            overflow: hidden;
	                        }
	                        .id-card::before {
	                            content: '';
	                            position: absolute;
	                            top: 0;
	                            left: 0;
	                            right: 0;
	                            bottom: 0;
	                            background: rgba(255, 255, 255, 0.1);
	                            z-index: 1;
	                        }
	                        .card-content {
	                            position: relative;
	                            z-index: 2;
	                            display: flex;
	                            height: 100%;
	                        }
	                        .photo-section {
	                            width: 2.5cm;
	                            height: 3.4cm;
	                            margin-right: 0.3cm;
	                            display: flex;
	                            flex-direction: column;
	                            align-items: center;
	                        }
	                        .student-photo {
	                            width: 2.2cm;
	                            height: 2.8cm;
	                            object-fit: cover;
	                            border: 2px solid white;
	                            border-radius: 0.2cm;
	                            transform: scaleX(-1);
	                        }
	                        .info-section {
	                            flex: 1;
	                            display: flex;
	                            flex-direction: column;
	                            justify-content: space-between;
	                        }
	                        .header {
	                            text-align: center;
	                            margin-bottom: 0.2cm;
	                        }
	                        .school-name {
	                            font-size: 0.4cm;
	                            font-weight: bold;
	                            margin-bottom: 0.1cm;
	                        }
	                        .card-title {
	                            font-size: 0.3cm;
	                            opacity: 0.9;
	                        }
	                        .student-info {
	                            flex: 1;
	                            display: flex;
	                            flex-direction: column;
	                            justify-content: center;
	                        }
	                        .info-row {
	                            margin-bottom: 0.15cm;
	                        }
	                        .info-label {
	                            font-size: 0.25cm;
	                            opacity: 0.8;
	                            margin-bottom: 0.05cm;
	                        }
	                        .info-value {
	                            font-size: 0.3cm;
	                            font-weight: bold;
	                        }
	                                                .qr-section {
                            position: absolute;
                            bottom: 0.3cm;
                            right: 0.3cm;
                            width: 1.4cm;
                            height: 1.4cm;
                            background: white;
                            padding: 0.1cm;
                            border-radius: 0.1cm;
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                        }
                        .qr-label {
                            font-size: 0.15cm;
                            color: #333;
                            margin-bottom: 0.05cm;
                            font-weight: bold;
                        }
	                                                .qr-code {
                            width: 1.2cm;
                            height: 1.2cm;
                            background: white;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            border-radius: 0.05cm;
                        }
	                        .footer {
	                            position: absolute;
	                            bottom: 0.1cm;
	                            left: 0.3cm;
	                            font-size: 0.2cm;
	                            opacity: 0.7;
	                        }
	                        @page {
	                            size: A4;
	                            margin: 0;
	                        }
	                    }
	                </style>
	            </head>
	            <body>
	                <div class="id-card">
	                    <div class="card-content">
	                        <div class="photo-section">
	                            <img src="${studentPhoto}" alt="Student Photo" class="student-photo">
	                        </div>
	                        <div class="info-section">
	                            <div class="header">
	                                <div class="school-name">SEAIT</div>
	                                <div class="card-title">STUDENT ID CARD</div>
	                            </div>
	                            <div class="student-info">
	                                <div class="info-row">
	                                    <div class="info-label">ID Number:</div>
	                                    <div class="info-value">${studentId}</div>
	                                </div>
	                                <div class="info-row">
	                                    <div class="info-label">Status:</div>
	                                    <div class="info-value">REGISTERED</div>
	                                </div>
	                                <div class="info-row">
	                                    <div class="info-label">Date:</div>
	                                    <div class="info-value">${new Date().toLocaleDateString()}</div>
	                                </div>
	                            </div>
	                        </div>
	                                                <div class="qr-section">
                            <div class="qr-label">QR</div>
                            <img src="backend_scripts/generate_qr.php?id=${studentId}" alt="QR Code" class="qr-code" style="width: 100%; height: 100%; object-fit: contain;">
                        </div>
	                    </div>
	                    <div class="footer">SEAIT - Student Entry and Exit Notification System</div>
	                </div>
	            </body>
	            </html>
	        `;
	        
	        // Open print window
	        var printWindow = window.open('', '_blank');
	        printWindow.document.write(printContent);
	        printWindow.document.close();
	        
	        // Wait for content to load then print
	        printWindow.onload = function() {
	            printWindow.print();
	            printWindow.close();
	        };
	        
	        // Show success message
	        Swal.fire({
	            title: 'Printing ID Card',
	            text: 'The ID card is being prepared for printing...',
	            icon: 'info',
	            timer: 2000,
	            showConfirmButton: false
	        });
	    });
	} );

	// RFID Writing functionality
	function writeRfidCard(studentId) {
		// Show confirmation dialog
		Swal.fire({
			title: 'Write RFID Code to Card',
			text: `Are you sure you want to write the RFID code for student ID ${studentId} to a card?`,
			icon: 'question',
			showCancelButton: true,
			confirmButtonColor: '#8b5cf6',
			cancelButtonColor: '#6b7280',
			confirmButtonText: 'Yes, write RFID code',
			cancelButtonText: 'Cancel',
			showLoaderOnConfirm: true,
			preConfirm: () => {
				return $.ajax({
					url: 'backend_scripts/write_rfid.php',
					method: 'POST',
					data: {
						'token': 'Seait123',
						'action': 'write_rfid',
						'student_id': studentId
					},
					dataType: 'json'
				}).then(response => {
					if (response.success === 1) {
						return response;
					} else {
						throw new Error(response.message || 'Failed to write RFID code');
					}
				}).catch(error => {
					Swal.showValidationMessage(`Request failed: ${error.message}`);
				});
			},
			allowOutsideClick: () => !Swal.isLoading()
		}).then((result) => {
			if (result.isConfirmed) {
				Swal.fire({
					title: 'Success!',
					text: result.value.message,
					icon: 'success',
					confirmButtonColor: '#8b5cf6'
				});
			}
		});
	}

	// RFID Service Status Management
	function refreshRFIDStatus() {
		$.ajax({
			url: 'rfid_service_manager.php',
			method: 'POST',
			data: {
				'action': 'get_status'
			},
			dataType: 'json',
			success: function(response) {
				updateRFIDStatusDisplay(response);
			},
			error: function(xhr, status, error) {
				console.error('Failed to get RFID status:', error);
				showRFIDStatusError();
			}
		});
	}

	function updateRFIDStatusDisplay(status) {
		const container = document.getElementById('rfid-status-container');
		
		container.innerHTML = `
			<div class="flex items-center space-x-2">
				<span class="text-xs text-gray-600">Python API:</span>
				<span class="w-2 h-2 rounded-full ${status.python_api.running ? 'bg-green-500' : 'bg-red-500'}"></span>
				<span class="text-xs ${status.python_api.running ? 'text-green-600' : 'text-red-600'}">${status.python_api.running ? 'Running' : 'Stopped'}</span>
			</div>
			
			<div class="flex items-center space-x-2">
				<span class="text-xs text-gray-600">Arduino:</span>
				<span class="w-2 h-2 rounded-full ${status.arduino_connection ? 'bg-green-500' : 'bg-red-500'}"></span>
				<span class="text-xs ${status.arduino_connection ? 'text-green-600' : 'text-red-600'}">${status.arduino_connection ? 'Connected' : 'Disconnected'}</span>
			</div>
			
			<div class="flex items-center space-x-2">
				<span class="text-xs text-gray-500">${status.last_check}</span>
			</div>
		`;
	}

	function showRFIDStatusError() {
		const container = document.getElementById('rfid-status-container');
		container.innerHTML = `
			<div class="flex items-center space-x-2">
				<span class="w-2 h-2 rounded-full bg-red-500"></span>
				<span class="text-xs text-red-600">Status unavailable</span>
			</div>
		`;
	}

	// Auto-refresh RFID status every 30 seconds
	setInterval(refreshRFIDStatus, 30000);

	// Initial RFID status check
	$(document).ready(function() {
		refreshRFIDStatus();
	});

	// Check RFID card status
	function checkRfidStatus(studentId) {
		$.ajax({
			url: 'backend_scripts/write_rfid.php',
			method: 'POST',
			data: {
				'token': 'Seait123',
				'action': 'check_rfid_status',
				'student_id': studentId
			},
			dataType: 'json',
			success: function(response) {
				if (response.success === 1) {
					Swal.fire({
						title: 'RFID Card Status',
						text: response.message,
						icon: 'success',
						confirmButtonColor: '#8b5cf6'
					});
				} else {
					Swal.fire({
						title: 'RFID Card Status',
						text: response.message,
						icon: 'warning',
						confirmButtonColor: '#8b5cf6'
					});
				}
			},
			error: function(xhr, status, error) {
				Swal.fire({
					title: 'Error',
					text: 'Failed to check RFID card status',
					icon: 'error',
					confirmButtonColor: '#8b5cf6'
				});
			}
		});
	}

	var playSound = (function beep() {
    var snd = new Audio("data:audio/wav;base64,//uQRAAAAWMSLwUIYAAsYkXgoQwAEaYLWfkWgAI0wWs/ItAAAGDgYtAgAyN+QWaAAihwMWm4G8QQRDiMcCBcH3Cc+CDv/7xA4Tvh9Rz/y8QADBwMWgQAZG/ILNAARQ4GLTcDeIIIhxGOBAuD7hOfBB3/94gcJ3w+o5/5eIAIAAAVwWgQAVQ2ORaIQwEMAJiDg95G4nQL7mQVWI6GwRcfsZAcsKkJvxgxEjzFUgfHoSQ9Qq7KNwqHwuB13MA4a1q/DmBrHgPcmjiGoh//EwC5nGPEmS4RcfkVKOhJf+WOgoxJclFz3kgn//dBA+ya1GhurNn8zb//9NNutNuhz31f////9vt///z+IdAEAAAK4LQIAKobHItEIYCGAExBwe8jcToF9zIKrEdDYIuP2MgOWFSE34wYiR5iqQPj0JIeoVdlG4VD4XA67mAcNa1fhzA1jwHuTRxDUQ//iYBczjHiTJcIuPyKlHQkv/LHQUYkuSi57yQT//uggfZNajQ3Vmz+Zt//+mm3Wm3Q576v////+32///5/EOgAAADVghQAAAAA//uQZAUAB1WI0PZugAAAAAoQwAAAEk3nRd2qAAAAACiDgAAAAAAABCqEEQRLCgwpBGMlJkIz8jKhGvj4k6jzRnqasNKIeoh5gI7BJaC1A1AoNBjJgbyApVS4IDlZgDU5WUAxEKDNmmALHzZp0Fkz1FMTmGFl1FMEyodIavcCAUHDWrKAIA4aa2oCgILEBupZgHvAhEBcZ6joQBxS76AgccrFlczBvKLC0QI2cBoCFvfTDAo7eoOQInqDPBtvrDEZBNYN5xwNwxQRfw8ZQ5wQVLvO8OYU+mHvFLlDh05Mdg7BT6YrRPpCBznMB2r//xKJjyyOh+cImr2/4doscwD6neZjuZR4AgAABYAAAABy1xcdQtxYBYYZdifkUDgzzXaXn98Z0oi9ILU5mBjFANmRwlVJ3/6jYDAmxaiDG3/6xjQQCCKkRb/6kg/wW+kSJ5//rLobkLSiKmqP/0ikJuDaSaSf/6JiLYLEYnW/+kXg1WRVJL/9EmQ1YZIsv/6Qzwy5qk7/+tEU0nkls3/zIUMPKNX/6yZLf+kFgAfgGyLFAUwY//uQZAUABcd5UiNPVXAAAApAAAAAE0VZQKw9ISAAACgAAAAAVQIygIElVrFkBS+Jhi+EAuu+lKAkYUEIsmEAEoMeDmCETMvfSHTGkF5RWH7kz/ESHWPAq/kcCRhqBtMdokPdM7vil7RG98A2sc7zO6ZvTdM7pmOUAZTnJW+NXxqmd41dqJ6mLTXxrPpnV8avaIf5SvL7pndPvPpndJR9Kuu8fePvuiuhorgWjp7Mf/PRjxcFCPDkW31srioCExivv9lcwKEaHsf/7ow2Fl1T/9RkXgEhYElAoCLFtMArxwivDJJ+bR1HTKJdlEoTELCIqgEwVGSQ+hIm0NbK8WXcTEI0UPoa2NbG4y2K00JEWbZavJXkYaqo9CRHS55FcZTjKEk3NKoCYUnSQ0rWxrZbFKbKIhOKPZe1cJKzZSaQrIyULHDZmV5K4xySsDRKWOruanGtjLJXFEmwaIbDLX0hIPBUQPVFVkQkDoUNfSoDgQGKPekoxeGzA4DUvnn4bxzcZrtJyipKfPNy5w+9lnXwgqsiyHNeSVpemw4bWb9psYeq//uQZBoABQt4yMVxYAIAAAkQoAAAHvYpL5m6AAgAACXDAAAAD59jblTirQe9upFsmZbpMudy7Lz1X1DYsxOOSWpfPqNX2WqktK0DMvuGwlbNj44TleLPQ+Gsfb+GOWOKJoIrWb3cIMeeON6lz2umTqMXV8Mj30yWPpjoSa9ujK8SyeJP5y5mOW1D6hvLepeveEAEDo0mgCRClOEgANv3B9a6fikgUSu/DmAMATrGx7nng5p5iimPNZsfQLYB2sDLIkzRKZOHGAaUyDcpFBSLG9MCQALgAIgQs2YunOszLSAyQYPVC2YdGGeHD2dTdJk1pAHGAWDjnkcLKFymS3RQZTInzySoBwMG0QueC3gMsCEYxUqlrcxK6k1LQQcsmyYeQPdC2YfuGPASCBkcVMQQqpVJshui1tkXQJQV0OXGAZMXSOEEBRirXbVRQW7ugq7IM7rPWSZyDlM3IuNEkxzCOJ0ny2ThNkyRai1b6ev//3dzNGzNb//4uAvHT5sURcZCFcuKLhOFs8mLAAEAt4UWAAIABAAAAAB4qbHo0tIjVkUU//uQZAwABfSFz3ZqQAAAAAngwAAAE1HjMp2qAAAAACZDgAAAD5UkTE1UgZEUExqYynN1qZvqIOREEFmBcJQkwdxiFtw0qEOkGYfRDifBui9MQg4QAHAqWtAWHoCxu1Yf4VfWLPIM2mHDFsbQEVGwyqQoQcwnfHeIkNt9YnkiaS1oizycqJrx4KOQjahZxWbcZgztj2c49nKmkId44S71j0c8eV9yDK6uPRzx5X18eDvjvQ6yKo9ZSS6l//8elePK/Lf//IInrOF/FvDoADYAGBMGb7FtErm5MXMlmPAJQVgWta7Zx2go+8xJ0UiCb8LHHdftWyLJE0QIAIsI+UbXu67dZMjmgDGCGl1H+vpF4NSDckSIkk7Vd+sxEhBQMRU8j/12UIRhzSaUdQ+rQU5kGeFxm+hb1oh6pWWmv3uvmReDl0UnvtapVaIzo1jZbf/pD6ElLqSX+rUmOQNpJFa/r+sa4e/pBlAABoAAAAA3CUgShLdGIxsY7AUABPRrgCABdDuQ5GC7DqPQCgbbJUAoRSUj+NIEig0YfyWUho1VBBBA//uQZB4ABZx5zfMakeAAAAmwAAAAF5F3P0w9GtAAACfAAAAAwLhMDmAYWMgVEG1U0FIGCBgXBXAtfMH10000EEEEEECUBYln03TTTdNBDZopopYvrTTdNa325mImNg3TTPV9q3pmY0xoO6bv3r00y+IDGid/9aaaZTGMuj9mpu9Mpio1dXrr5HERTZSmqU36A3CumzN/9Robv/Xx4v9ijkSRSNLQhAWumap82WRSBUqXStV/YcS+XVLnSS+WLDroqArFkMEsAS+eWmrUzrO0oEmE40RlMZ5+ODIkAyKAGUwZ3mVKmcamcJnMW26MRPgUw6j+LkhyHGVGYjSUUKNpuJUQoOIAyDvEyG8S5yfK6dhZc0Tx1KI/gviKL6qvvFs1+bWtaz58uUNnryq6kt5RzOCkPWlVqVX2a/EEBUdU1KrXLf40GoiiFXK///qpoiDXrOgqDR38JB0bw7SoL+ZB9o1RCkQjQ2CBYZKd/+VJxZRRZlqSkKiws0WFxUyCwsKiMy7hUVFhIaCrNQsKkTIsLivwKKigsj8XYlwt/WKi2N4d//uQRCSAAjURNIHpMZBGYiaQPSYyAAABLAAAAAAAACWAAAAApUF/Mg+0aohSIRobBAsMlO//Kk4soosy1JSFRYWaLC4qZBYWFRGZdwqKiwkNBVmoWFSJkWFxX4FFRQWR+LsS4W/rFRb/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////VEFHAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAU291bmRib3kuZGUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMjAwNGh0dHA6Ly93d3cuc291bmRib3kuZGUAAAAAAAAAACU=");  
    	return function() {     
        	snd.play(); 
    	}
	})();

</script>
</html>