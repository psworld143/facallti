<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get the error type and additional information
$error_type = $_SERVER['REDIRECT_STATUS'] ?? '505';
$requested_url = $_SERVER['REQUEST_URI'];
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct Access';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Set page title and error message
$page_title = 'Page Not Working - 505 Error';
$error_number = '505';
$error_title = 'Page Not Working';
$error_message = 'This page is currently experiencing technical difficulties. Our team has been notified and is working to resolve the issue.';
$error_color = 'from-red-400 via-red-500 to-red-600';

// Log the error for debugging
$log_query = "INSERT INTO error_logs (error_type, requested_url, referrer, user_agent, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
$log_stmt = mysqli_prepare($conn, $log_query);
if ($log_stmt) {
    mysqli_stmt_bind_param($log_stmt, "sssss", $error_type, $requested_url, $referrer, $user_agent, $ip_address);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);
}

// Get current timestamp for cache busting
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SEAIT</title>
    <link rel="icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .animate-blob {
            animation: blob 7s infinite;
        }
        .animation-delay-2000 {
            animation-delay: 2s;
        }
        .animation-delay-4000 {
            animation-delay: 4s;
        }
        @keyframes blob {
            0% {
                transform: translate(0px, 0px) scale(1);
            }
            33% {
                transform: translate(30px, -50px) scale(1.1);
            }
            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }
            100% {
                transform: translate(0px, 0px) scale(1);
            }
        }
        .pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite alternate;
        }
        @keyframes pulse-glow {
            from {
                box-shadow: 0 0 20px rgba(239, 68, 68, 0.4);
            }
            to {
                box-shadow: 0 0 30px rgba(239, 68, 68, 0.8);
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- 505 Error Page -->
<div class="min-h-screen bg-gradient-to-br from-slate-900 via-red-900 to-slate-900 relative overflow-hidden">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0">
        <div class="absolute top-20 left-20 w-72 h-72 bg-red-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div class="absolute top-40 right-20 w-72 h-72 bg-orange-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-40 w-72 h-72 bg-yellow-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-12">
        <div class="max-w-6xl mx-auto text-center">
            <!-- Main Error Section -->
            <div class="mb-12">
                <!-- Animated Error Number -->
                <div class="relative mb-8">
                    <h1 class="text-9xl md:text-[12rem] font-black text-transparent bg-clip-text bg-gradient-to-r <?php echo $error_color; ?> animate-pulse">
                        <?php echo $error_number; ?>
                    </h1>
                    <div class="absolute inset-0 text-9xl md:text-[12rem] font-black text-gray-800 opacity-10 -z-10 animate-ping">
                        <?php echo $error_number; ?>
                    </div>
                </div>

                <!-- Error Message -->
                <div class="space-y-6 mb-12">
                    <h2 class="text-4xl md:text-5xl font-bold text-white mb-4">
                        Oops! <?php echo $error_title; ?>
                    </h2>
                    <p class="text-xl text-gray-300 max-w-3xl mx-auto leading-relaxed">
                        <?php echo $error_message; ?>
                    </p>
                </div>

                <!-- Technical Details -->
                <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 mb-12 max-w-3xl mx-auto border border-white/20">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center justify-center">
                        <i class="fas fa-tools mr-2"></i>Technical Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-300">
                        <div class="text-left">
                            <p><strong>Requested URL:</strong></p>
                            <p class="font-mono bg-black/20 p-2 rounded mt-1 break-all"><?php echo htmlspecialchars($requested_url); ?></p>
                        </div>
                        <div class="text-left">
                            <p><strong>Error Type:</strong></p>
                            <p class="font-mono bg-black/20 p-2 rounded mt-1"><?php echo htmlspecialchars($error_type); ?></p>
                        </div>
                        <div class="text-left">
                            <p><strong>Timestamp:</strong></p>
                            <p class="font-mono bg-black/20 p-2 rounded mt-1"><?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                        <div class="text-left">
                            <p><strong>Error ID:</strong></p>
                            <p class="font-mono bg-black/20 p-2 rounded mt-1"><?php echo uniqid('ERR_'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Troubleshooting Steps -->
                <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 mb-12 max-w-4xl mx-auto border border-white/20">
                    <h3 class="text-lg font-semibold text-white mb-6 flex items-center justify-center">
                        <i class="fas fa-lightbulb mr-2"></i>Troubleshooting Steps
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4 pulse-glow">
                                <i class="fas fa-sync-alt text-2xl text-red-400"></i>
                            </div>
                            <h4 class="text-white font-semibold mb-2">Refresh the Page</h4>
                            <p class="text-gray-300 text-sm">Try refreshing the page to see if the issue resolves</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-orange-500/20 rounded-full flex items-center justify-center mx-auto mb-4 pulse-glow">
                                <i class="fas fa-clock text-2xl text-orange-400"></i>
                            </div>
                            <h4 class="text-white font-semibold mb-2">Wait a Moment</h4>
                            <p class="text-gray-300 text-sm">The issue might be temporary. Try again in a few minutes</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto mb-4 pulse-glow">
                                <i class="fas fa-home text-2xl text-yellow-400"></i>
                            </div>
                            <h4 class="text-white font-semibold mb-2">Go to Homepage</h4>
                            <p class="text-gray-300 text-sm">Navigate to the homepage and try accessing the page again</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
                    <button onclick="window.location.reload()" 
                            class="bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-4 rounded-xl hover:from-red-600 hover:to-red-700 transform transition-all hover:scale-105 font-semibold shadow-lg flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh Page
                    </button>
                    <a href="/seait/" 
                       class="bg-gradient-to-r from-seait-orange to-orange-500 text-white px-8 py-4 rounded-xl hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-semibold shadow-lg flex items-center">
                        <i class="fas fa-home mr-2"></i>Go to Homepage
                    </a>
                    <button onclick="history.back()" 
                            class="bg-gradient-to-r from-gray-600 to-gray-700 text-white px-8 py-4 rounded-xl hover:from-gray-700 hover:to-gray-800 transform transition-all hover:scale-105 font-semibold shadow-lg flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>Go Back
                    </button>
                </div>

                <!-- Contact Support -->
                <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 max-w-2xl mx-auto border border-white/20">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center justify-center">
                        <i class="fas fa-headset mr-2"></i>Need Help?
                    </h3>
                    <p class="text-gray-300 mb-4">
                        If the problem persists, please contact our technical support team with the Error ID above.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="mailto:mposebando@ndmu.edu.ph" 
                           class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-lg hover:from-blue-600 hover:to-blue-700 transform transition-all hover:scale-105 font-medium flex items-center justify-center">
                            <i class="fas fa-envelope mr-2"></i>Email Support
                        </a>
                        <a href="tel:+639600338862" 
                           class="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transform transition-all hover:scale-105 font-medium flex items-center justify-center">
                            <i class="fas fa-phone mr-2"></i>Call Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Auto-refresh script -->
<script>
// Auto-refresh after 30 seconds if user hasn't interacted
let refreshTimer;
let userInteracted = false;

function resetTimer() {
    clearTimeout(refreshTimer);
    if (!userInteracted) {
        refreshTimer = setTimeout(() => {
            window.location.reload();
        }, 30000); // 30 seconds
    }
}

// Reset timer on user interaction
document.addEventListener('click', () => {
    userInteracted = true;
    clearTimeout(refreshTimer);
});

document.addEventListener('keydown', () => {
    userInteracted = true;
    clearTimeout(refreshTimer);
});

// Start the timer
resetTimer();

// Show notification about auto-refresh
setTimeout(() => {
    if (!userInteracted) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        notification.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Page will auto-refresh in 30 seconds';
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 5000);
    }
}, 5000);
</script>

</body>
</html>
