<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set page title
$page_title = 'Page Not Found - 404 Error';

// Get the requested URL for logging
$requested_url = $_SERVER['REQUEST_URI'];
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct Access';

// Log the 404 error for analytics
$log_query = "INSERT INTO error_logs (error_type, requested_url, referrer, user_agent, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
$log_stmt = mysqli_prepare($conn, $log_query);
if ($log_stmt) {
    $error_type = '404';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    mysqli_stmt_bind_param($log_stmt, "sssss", $error_type, $requested_url, $referrer, $user_agent, $ip_address);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SEAIT</title>
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
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    <script src="assets/js/dark-mode.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- 404 Error Page -->
<div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0">
        <div class="absolute top-20 left-20 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div class="absolute top-40 right-20 w-72 h-72 bg-yellow-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-40 w-72 h-72 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-12">
        <div class="max-w-6xl mx-auto text-center">
            <!-- Main Error Section -->
            <div class="mb-12">
                <!-- Animated 404 Number -->
                <div class="relative mb-8">
                    <h1 class="text-9xl md:text-[12rem] font-black text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-pink-500 to-red-500 animate-pulse">
                        404
                    </h1>
                    <div class="absolute inset-0 text-9xl md:text-[12rem] font-black text-gray-800 opacity-10 -z-10 animate-ping">
                        404
                    </div>
                </div>

                <!-- Error Message -->
                <div class="space-y-6 mb-12">
                    <h2 class="text-4xl md:text-5xl font-bold text-white mb-4">
                        Oops! Page Not Found
                    </h2>
                    <p class="text-xl text-gray-300 max-w-3xl mx-auto leading-relaxed">
                        The page you're looking for seems to have wandered off into the digital wilderness. 
                        Don't worry, we'll help you find your way back!
                    </p>
                </div>

                <!-- Requested URL Display -->
                <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 mb-12 max-w-3xl mx-auto border border-white/20">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center justify-center">
                        <i class="fas fa-link mr-2 text-purple-400"></i>
                        Requested URL
                    </h3>
                    <div class="bg-gray-900/50 rounded-xl p-4 text-left border border-gray-700">
                        <code class="text-sm text-purple-300 break-all font-mono">
                            <?php echo htmlspecialchars($requested_url); ?>
                        </code>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center mb-16">
                <a href="index.php" 
                   class="group relative inline-flex items-center px-8 py-4 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:from-purple-500 hover:to-pink-500 transform transition-all duration-300 hover:scale-105 font-semibold shadow-2xl hover:shadow-purple-500/25">
                    <div class="absolute inset-0 bg-gradient-to-r from-purple-600 to-pink-600 rounded-xl blur opacity-75 group-hover:opacity-100 transition duration-300"></div>
                    <span class="relative flex items-center">
                        <i class="fas fa-home mr-3 text-lg"></i>
                        Go to Homepage
                    </span>
                </a>
                
                <button onclick="history.back()" 
                        class="group relative inline-flex items-center px-8 py-4 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-500 hover:to-gray-600 transform transition-all duration-300 hover:scale-105 font-semibold shadow-2xl hover:shadow-gray-500/25">
                    <div class="absolute inset-0 bg-gradient-to-r from-gray-600 to-gray-700 rounded-xl blur opacity-75 group-hover:opacity-100 transition duration-300"></div>
                    <span class="relative flex items-center">
                        <i class="fas fa-arrow-left mr-3 text-lg"></i>
                        Go Back
                    </span>
                </button>
                
                <a href="contact.php" 
                   class="group relative inline-flex items-center px-8 py-4 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-xl hover:from-blue-500 hover:to-cyan-500 transform transition-all duration-300 hover:scale-105 font-semibold shadow-2xl hover:shadow-blue-500/25">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-cyan-600 rounded-xl blur opacity-75 group-hover:opacity-100 transition duration-300"></div>
                    <span class="relative flex items-center">
                        <i class="fas fa-envelope mr-3 text-lg"></i>
                        Contact Support
                    </span>
                </a>
            </div>

            <!-- Quick Links Section -->
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 max-w-5xl mx-auto border border-white/20 mb-12">
                <h3 class="text-2xl font-bold text-white mb-8 flex items-center justify-center">
                    <i class="fas fa-compass mr-3 text-purple-400"></i>
                    Popular Destinations
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="about.php" class="group p-6 bg-gradient-to-br from-purple-500/20 to-pink-500/20 rounded-xl hover:from-purple-500/30 hover:to-pink-500/30 transition-all duration-300 border border-purple-500/30 hover:border-purple-400/50 transform hover:scale-105">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-info-circle text-white text-2xl"></i>
                            </div>
                            <div class="font-bold text-white text-lg mb-2">About Us</div>
                            <div class="text-gray-300 text-sm">Discover our story and mission</div>
                        </div>
                    </a>
                    
                    <a href="services.php" class="group p-6 bg-gradient-to-br from-green-500/20 to-emerald-500/20 rounded-xl hover:from-green-500/30 hover:to-emerald-500/30 transition-all duration-300 border border-green-500/30 hover:border-green-400/50 transform hover:scale-105">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-gradient-to-r from-green-500 to-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-cogs text-white text-2xl"></i>
                            </div>
                            <div class="font-bold text-white text-lg mb-2">Services</div>
                            <div class="text-gray-300 text-sm">Explore what we offer</div>
                        </div>
                    </a>
                    
                    <a href="news.php" class="group p-6 bg-gradient-to-br from-blue-500/20 to-cyan-500/20 rounded-xl hover:from-blue-500/30 hover:to-cyan-500/30 transition-all duration-300 border border-blue-500/30 hover:border-blue-400/50 transform hover:scale-105">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-newspaper text-white text-2xl"></i>
                            </div>
                            <div class="font-bold text-white text-lg mb-2">News & Updates</div>
                            <div class="text-gray-300 text-sm">Stay in the loop</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Search Section -->
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 max-w-3xl mx-auto border border-white/20">
                <h3 class="text-2xl font-bold text-white mb-6 flex items-center justify-center">
                    <i class="fas fa-search mr-3 text-purple-400"></i>
                    Find What You're Looking For
                </h3>
                <form action="search.php" method="GET" class="flex gap-4">
                    <div class="flex-1 relative">
                        <input type="text" name="q" placeholder="Search for pages, services, or information..." 
                               class="w-full px-6 py-4 bg-white/20 backdrop-blur-sm border-2 border-white/30 rounded-xl focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-400/50 transition-all duration-300 text-white placeholder-gray-300">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                    <button type="submit" 
                            class="px-8 py-4 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:from-purple-500 hover:to-pink-500 transform transition-all duration-300 hover:scale-105 font-semibold shadow-lg hover:shadow-purple-500/25">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for animations -->
<style>
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

.animate-blob {
    animation: blob 7s infinite;
}

.animation-delay-2000 {
    animation-delay: 2s;
}

.animation-delay-4000 {
    animation-delay: 4s;
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(to bottom, #8b5cf6, #ec4899);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(to bottom, #7c3aed, #db2777);
}
</style>

<!-- Error Reporting Script -->
<script>
// Report error to analytics (if you have Google Analytics or similar)
if (typeof gtag !== 'undefined') {
    gtag('event', 'page_view', {
        page_title: '404 Error Page',
        page_location: window.location.href
    });
}

// Log error to console for debugging
console.error('404 Error: Page not found at', window.location.href);

// Auto-redirect after 30 seconds (optional)
setTimeout(function() {
    if (confirm('Would you like to be redirected to the homepage?')) {
        window.location.href = 'index.php';
    }
}, 30000);
</script>

</body>
</html>
