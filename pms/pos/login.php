<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../includes/database.php';

// Redirect if already logged in to POS
if (isset($_SESSION['pos_user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $login_type = $_POST['login_type'];
    
    try {
        if ($login_type === 'pms') {
            // PMS User Login - Check if user exists and has POS access
            $stmt = $pdo->prepare("SELECT id, name, username, password, role FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if user has POS access based on role
                $pos_roles = ['manager', 'front_desk', 'housekeeping', 'pos_user'];
                if (in_array($user['role'], $pos_roles)) {
                    $_SESSION['pos_user_id'] = $user['id'];
                    $_SESSION['pos_user_name'] = $user['name'];
                    $_SESSION['pos_user_role'] = $user['role'];
                    $_SESSION['pos_login_type'] = 'pms';
                    
                    // Log POS login
                    logPOSActivity($user['id'], 'login', 'PMS user logged into POS system');
                    
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Your role does not have access to POS system';
                }
            } else {
                $error = 'Invalid username or password';
            }
            
        } elseif ($login_type === 'student') {
            // Student Simulation Login - Use demo credentials
            $student_credentials = [
                'student1' => 'password123',
                'student2' => 'password123',
                'student3' => 'password123',
                'demo_user' => 'demo123'
            ];
            
            if (isset($student_credentials[$username]) && $student_credentials[$username] === $password) {
                $_SESSION['pos_user_id'] = 'student_' . $username;
                $_SESSION['pos_user_name'] = 'Student ' . ucfirst($username);
                $_SESSION['pos_user_role'] = 'student';
                $_SESSION['pos_login_type'] = 'student';
                $_SESSION['pos_demo_mode'] = true;
                
                // Log student login
                logPOSActivity('student_' . $username, 'login', 'Student logged into POS simulation');
                
                $success = 'Welcome to POS Simulation Mode!';
                header('Location: index.php');
                exit();
            } else {
                $error = 'Invalid student credentials';
            }
        }
        
    } catch (PDOException $e) {
        error_log("POS Login error: " . $e->getMessage());
        $error = 'System error. Please try again.';
    }
}

// Function to log POS activities
function logPOSActivity($user_id, $action, $description) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO pos_activity_log (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (PDOException $e) {
        error_log("Error logging POS activity: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System Login - Hotel Management Simulation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#667eea',
                        secondary: '#764ba2',
                        accent: '#f093fb',
                        success: '#4ade80',
                        warning: '#fbbf24',
                        error: '#f87171'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-in-out',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'bounce-in': 'bounceIn 0.8s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                        'float': 'float 3s ease-in-out infinite'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(30px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        bounceIn: {
                            '0%': { transform: 'scale(0.3)', opacity: '0' },
                            '50%': { transform: 'scale(1.05)' },
                            '70%': { transform: 'scale(0.9)' },
                            '100%': { transform: 'scale(1)', opacity: '1' }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .input-focus:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="min-h-screen bg-white relative overflow-hidden">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-gradient-to-br from-primary/10 to-secondary/10 rounded-full blur-3xl animate-float"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-gradient-to-tr from-accent/10 to-primary/10 rounded-full blur-3xl animate-float" style="animation-delay: 1.5s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-gradient-to-r from-secondary/5 to-accent/5 rounded-full blur-3xl animate-pulse-slow"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md animate-fade-in">
            <!-- Main Login Card -->
            <div class="bg-white rounded-3xl shadow-2xl p-8 animate-slide-up card-hover transition-all duration-500 border border-gray-100">
                <!-- Header Section -->
                <div class="text-center mb-8 animate-bounce-in">
                    <div class="relative">
                        <div class="w-20 h-20 bg-gradient-to-r from-primary via-secondary to-accent rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg animate-pulse-slow">
                            <i class="fas fa-cash-register text-white text-3xl"></i>
                        </div>
                        <div class="absolute -top-2 -right-2 w-6 h-6 bg-success rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-white text-xs"></i>
                        </div>
                    </div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
                        POS System
                    </h1>
                    <p class="text-gray-600 mt-2 text-sm font-medium">Hotel Management Simulation & Learning</p>
                    <div class="flex items-center justify-center mt-3 space-x-2">
                        <div class="w-2 h-2 bg-success rounded-full animate-pulse"></div>
                        <span class="text-success text-xs font-medium">System Online</span>
                    </div>
                </div>
                
                <!-- Error/Success Messages -->
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center animate-bounce-in">
                    <i class="fas fa-exclamation-triangle mr-3 text-red-500"></i>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center animate-bounce-in">
                    <i class="fas fa-check-circle mr-3 text-green-500"></i>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" class="space-y-6 animate-slide-up" style="animation-delay: 0.2s;">
                    <!-- Login Type Selection -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="fas fa-users mr-2 text-primary"></i>Login Type
                            <button type="button" onclick="showLoginTypeInfo()" class="ml-2 text-blue-500 hover:text-blue-700 transition-colors">
                                <i class="fas fa-info-circle text-sm"></i>
                            </button>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center p-3 border border-gray-300 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="radio" name="login_type" value="pms" class="mr-2 text-primary" checked>
                                <span class="text-sm font-medium">PMS User</span>
                            </label>
                            <label class="flex items-center p-3 border border-gray-300 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="radio" name="login_type" value="student" class="mr-2 text-primary">
                                <span class="text-sm font-medium">Student Demo</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="fas fa-user mr-2 text-primary"></i>Username
                        </label>
                        <div class="relative">
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   class="w-full px-4 py-4 bg-gray-50 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-500 input-focus"
                                   placeholder="Enter your username">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="fas fa-lock mr-2 text-primary"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                   class="w-full px-4 py-4 bg-gray-50 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-500 input-focus"
                                   placeholder="Enter your password">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-gradient-to-r from-primary via-secondary to-accent text-white py-4 px-6 rounded-xl font-semibold btn-hover transition-all duration-300 shadow-lg relative overflow-hidden group">
                        <span class="relative z-10 flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-2"></i>Sign In to POS
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
                    </button>
                </form>

                <!-- Demo Credentials -->
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                    <h4 class="text-sm font-medium text-blue-800 mb-2 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        Student Demo Credentials
                    </h4>
                    <div class="text-xs text-blue-700 space-y-1">
                        <div><strong>Username:</strong> student1, student2, student3, demo_user</div>
                        <div><strong>Password:</strong> password123 (for students), demo123 (for demo)</div>
                    </div>
                </div>
            </div>

            <!-- Info Modal -->
            <div id="infoModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 animate-slide-up">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-info-circle text-primary mr-3"></i>
                            Login Type Information
                        </h3>
                        <button onclick="hideLoginTypeInfo()" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Modal Content -->
                    <div class="p-6 space-y-6">
                        <!-- PMS User Section -->
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <h4 class="font-semibold text-blue-900 mb-3 flex items-center">
                                <i class="fas fa-user-tie mr-2"></i>
                                PMS User Login
                            </h4>
                            <div class="text-blue-800 text-sm space-y-2">
                                <p><strong>Purpose:</strong> Real hotel staff access to POS system</p>
                                <p><strong>Access:</strong> Production data, actual transactions</p>
                                <p><strong>Users:</strong> Managers, front desk, housekeeping staff</p>
                                <p><strong>Features:</strong> Full PMS integration, real guest data</p>
                                <p><strong>Security:</strong> Role-based permissions, audit trail</p>
                            </div>
                        </div>

                        <!-- Student Demo Section -->
                        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                            <h4 class="font-semibold text-green-900 mb-3 flex items-center">
                                <i class="fas fa-graduation-cap mr-2"></i>
                                Student Demo Login
                            </h4>
                            <div class="text-green-800 text-sm space-y-2">
                                <p><strong>Purpose:</strong> Safe learning environment for students</p>
                                <p><strong>Access:</strong> Demo environment, practice scenarios</p>
                                <p><strong>Users:</strong> Hospitality students, trainees</p>
                                <p><strong>Features:</strong> Full POS functionality, no real data risk</p>
                                <p><strong>Benefits:</strong> Progress tracking, learning analytics</p>
                            </div>
                        </div>

                        <!-- Key Benefits Section -->
                        <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
                            <h4 class="font-semibold text-purple-900 mb-3 flex items-center">
                                <i class="fas fa-star mr-2"></i>
                                Key Benefits
                            </h4>
                            <div class="text-purple-800 text-sm space-y-2">
                                <p><strong>Safety:</strong> Students can't accidentally affect real operations</p>
                                <p><strong>Learning:</strong> Realistic practice environment</p>
                                <p><strong>Efficiency:</strong> Training doesn't interfere with business</p>
                                <p><strong>Compliance:</strong> Maintains data security standards</p>
                                <p><strong>Scalability:</strong> Multiple students can practice simultaneously</p>
                            </div>
                        </div>

                        <!-- Educational Scenarios -->
                        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                            <h4 class="font-semibold text-orange-900 mb-3 flex items-center">
                                <i class="fas fa-chalkboard-teacher mr-2"></i>
                                Educational Scenarios
                            </h4>
                            <div class="text-orange-800 text-sm space-y-2">
                                <p><strong>Restaurant POS:</strong> Order taking, payment processing</p>
                                <p><strong>Room Service:</strong> In-room dining scenarios</p>
                                <p><strong>Spa Services:</strong> Appointment booking, service management</p>
                                <p><strong>Gift Shop:</strong> Retail transactions, inventory</p>
                                <p><strong>Event Management:</strong> Catering, AV services, guest management</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="flex justify-end p-6 border-t border-gray-200">
                        <button onclick="hideLoginTypeInfo()" class="bg-primary text-white px-6 py-2 rounded-xl hover:bg-primary-dark transition-colors">
                            Got it!
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-center mt-6 animate-fade-in" style="animation-delay: 0.6s;">
                <p class="text-gray-500 text-sm mb-2">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Secure POS login system with encrypted authentication
                </p>
                <a href="../booking/" class="text-primary hover:text-primary-dark transition-colors text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>
                    Back to PMS System
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Add interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation feedback
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.outline = 'none';
                    this.style.borderColor = '#667eea';
                });
                
                input.addEventListener('blur', function() {
                    this.style.borderColor = '#d1d5db';
                });
            });

            // Dynamic form behavior based on login type
            document.querySelectorAll('input[name="login_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const isStudent = this.value === 'student';
                    const usernameField = document.getElementById('username');
                    const passwordField = document.getElementById('password');
                    
                    if (isStudent) {
                        usernameField.placeholder = 'Enter student username';
                        passwordField.placeholder = 'Enter student password';
                    } else {
                        usernameField.placeholder = 'Enter your username';
                        passwordField.placeholder = 'Enter your password';
                    }
                });
            });
        });

        // Info Modal Functions
        function showLoginTypeInfo() {
            const modal = document.getElementById('infoModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function hideLoginTypeInfo() {
            const modal = document.getElementById('infoModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside
        document.getElementById('infoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideLoginTypeInfo();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLoginTypeInfo();
            }
        });
    </script>
</body>
</html>
