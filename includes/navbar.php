<?php
// Navbar component for SEAIT website
// This file should be included in all pages for consistent navigation
?>
<!-- Navigation -->
<nav class="bg-transparent dark:bg-transparent sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-2 sm:px-4">
        <div class="flex items-center justify-between py-3 md:py-4">
            <!-- Logo and Brand - Leftmost Position -->
            <div class="flex items-center space-x-2 md:space-x-4 flex-shrink-0">
                <img src="assets/images/seait-logo.png" alt="SEAIT Logo" class="h-6 sm:h-8 md:h-12 w-auto">
                <div class="min-w-0">
                    <h1 class="text-sm sm:text-lg md:text-2xl font-bold text-seait-dark truncate">SEAIT</h1>
                    <!-- <p class="text-xs md:text-sm text-gray-600 hidden sm:block truncate">South East Asian Institute of Technology, Inc.</p> -->
                </div>
            </div>

            <!-- Center Navigation - Desktop -->
            <div class="hidden lg:flex items-center justify-center flex-1 px-4 ml-8">
                <div class="flex items-center space-x-2 xl:space-x-3 flex-nowrap">
                    <a href="index.php#home" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-sm xl:text-base px-1 py-1">Home</a>
                    <a href="index.php#about" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-sm xl:text-base px-1 py-1">About</a>
                    <a href="index.php#academics" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-sm xl:text-base px-1 py-1">Academics</a>
                    <a href="index.php#admissions" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-sm xl:text-base px-1 py-1">Admissions</a>
                    <a href="index.php#research" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-sm xl:text-base px-1 py-1">Research & Publication</a>
                    <a href="index.php#news" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-sm xl:text-base px-1 py-1">News</a>
                    <a href="index.php#contact" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-sm xl:text-base px-1 py-1">Contact</a>

                    <!-- Services Dropdown -->
                    <div class="relative group">
                        <button class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-sm xl:text-base px-1 py-1 flex items-center">
                            Services
                            <i class="fas fa-chevron-down ml-1 text-xs transition-transform group-hover:rotate-180"></i>
                        </button>
                        <div class="absolute top-full left-0 mt-1 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="py-2">
                                <a href="services.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-th-large mr-2"></i>All Services
                                </a>
                                <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
                                <a href="calendar.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-calendar-alt mr-2"></i>School Calendar
                                </a>
                                <a href="services.php?category=1" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-graduation-cap mr-2"></i>Academic Services
                                </a>
                                <a href="services.php?category=2" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-user-graduate mr-2"></i>Student Support
                                </a>
                                <a href="services.php?category=3" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-laptop mr-2"></i>Technology Services
                                </a>
                                <a href="services.php?category=4" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-heartbeat mr-2"></i>Health & Wellness
                                </a>
                                <a href="services.php?category=5" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-bus mr-2"></i>Transportation
                                </a>
                                <a href="services.php?category=6" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-desktop mr-2"></i>Virtual Learning
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center Navigation - Tablet -->
            <div class="hidden md:flex lg:hidden items-center justify-center flex-1 px-2 ml-4">
                <div class="flex items-center space-x-1 flex-nowrap">
                    <a href="index.php#home" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-xs px-1 py-1">Home</a>
                    <a href="index.php#about" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-xs px-1 py-1">About</a>
                    <a href="index.php#academics" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-xs px-1 py-1">Academics</a>
                    <a href="index.php#admissions" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-xs px-1 py-1">Admissions</a>
                    <a href="index.php#research" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-xs px-1 py-1">Research</a>
                    <a href="index.php#news" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-xs px-1 py-1">News</a>
                    <a href="index.php#contact" class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-xs px-1 py-1">Contact</a>
                    <!-- Services Dropdown - Tablet -->
                    <div class="relative group">
                        <button class="text-seait-dark dark:text-white hover:text-seait-orange transition whitespace-nowrap text-xs px-1 py-1 flex items-center">
                            Services
                            <i class="fas fa-chevron-down ml-1 text-xs transition-transform group-hover:rotate-180"></i>
                        </button>
                        <div class="absolute top-full left-0 mt-1 w-40 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="py-2">
                                <a href="services.php" class="block px-3 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-th-large mr-2"></i>All Services
                                </a>
                                <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
                                <a href="calendar.php" class="block px-3 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-calendar-alt mr-2"></i>School Calendar
                                </a>
                                <a href="services.php?category=1" class="block px-3 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-graduation-cap mr-2"></i>Academic
                                </a>
                                <a href="services.php?category=2" class="block px-3 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-user-graduate mr-2"></i>Student Support
                                </a>
                                <a href="services.php?category=3" class="block px-3 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-laptop mr-2"></i>Technology
                                </a>
                                <a href="services.php?category=4" class="block px-3 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-heartbeat mr-2"></i>Health
                                </a>
                                <a href="services.php?category=5" class="block px-3 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-bus mr-2"></i>Transport
                                </a>
                                <a href="services.php?category=6" class="block px-3 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-seait-orange hover:text-white transition">
                                    <i class="fas fa-desktop mr-2"></i>Virtual Learning
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right side buttons -->
            <div class="flex items-center space-x-2 md:space-x-3 flex-shrink-0">
                <!-- Dark Mode Toggle -->
                <button class="theme-toggle" title="Toggle Dark Mode (Ctrl+J)">
                    <i class="fas fa-moon icon"></i>
                    <i class="fas fa-sun icon"></i>
                </button>

                <button onclick="openLoginModal()" class="bg-seait-orange text-white px-2 sm:px-3 md:px-4 py-1.5 md:py-2 rounded hover:bg-orange-600 transition text-xs sm:text-sm md:text-base whitespace-nowrap">Login</button>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-button" class="md:hidden text-seait-dark dark:text-white hover:text-seait-orange transition p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                    <i class="fas fa-bars text-lg sm:text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white dark:bg-gray-800 py-3 relative z-30">
            <div class="grid grid-cols-2 gap-2 px-4">
                <a href="index.php#home" class="text-seait-dark dark:text-white hover:text-seait-orange transition py-2 px-3 text-center text-sm border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">Home</a>
                <a href="index.php#about" class="text-seait-dark dark:text-white hover:text-seait-orange transition py-2 px-3 text-center text-sm border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">About</a>
                <a href="index.php#academics" class="text-seait-dark dark:text-white hover:text-seait-orange transition py-2 px-3 text-center text-sm border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">Academics</a>
                <a href="index.php#admissions" class="text-seait-dark dark:text-white hover:text-seait-orange transition py-2 px-3 text-center text-sm border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">Admissions</a>
                <a href="index.php#research" class="text-seait-dark dark:text-white hover:text-seait-orange transition py-2 px-3 text-center text-sm border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">Research</a>
                <a href="index.php#news" class="text-seait-dark dark:text-white hover:text-seait-orange transition py-2 px-3 text-center text-sm border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">News</a>
                <a href="index.php#contact" class="text-seait-dark dark:text-white hover:text-seait-orange transition py-2 px-3 text-center text-sm border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">Contact</a>
            </div>

            <!-- Mobile Services Dropdown -->
            <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4 px-4">
                <button id="mobile-services-toggle" class="w-full text-left text-seait-dark dark:text-white hover:text-seait-orange transition py-2 px-3 text-sm font-medium flex items-center justify-between">
                    <span>Services</span>
                    <i class="fas fa-chevron-down transition-transform" id="mobile-services-icon"></i>
                </button>
                <div id="mobile-services-menu" class="hidden mt-2 space-y-1">
                    <a href="services.php" class="block py-2 px-6 text-sm text-gray-600 dark:text-gray-300 hover:text-seait-orange transition">
                        <i class="fas fa-th-large mr-2"></i>All Services
                    </a>
                    <a href="calendar.php" class="block py-2 px-6 text-sm text-gray-600 dark:text-gray-300 hover:text-seait-orange transition">
                        <i class="fas fa-calendar-alt mr-2"></i>School Calendar
                    </a>
                    <a href="services.php?category=1" class="block py-2 px-6 text-sm text-gray-600 dark:text-gray-300 hover:text-seait-orange transition">
                        <i class="fas fa-graduation-cap mr-2"></i>Academic Services
                    </a>
                    <a href="services.php?category=2" class="block py-2 px-6 text-sm text-gray-600 dark:text-gray-300 hover:text-seait-orange transition">
                        <i class="fas fa-user-graduate mr-2"></i>Student Support
                    </a>
                    <a href="services.php?category=3" class="block py-2 px-6 text-sm text-gray-600 dark:text-gray-300 hover:text-seait-orange transition">
                        <i class="fas fa-laptop mr-2"></i>Technology Services
                    </a>
                    <a href="services.php?category=4" class="block py-2 px-6 text-sm text-gray-600 dark:text-gray-300 hover:text-seait-orange transition">
                        <i class="fas fa-heartbeat mr-2"></i>Health & Wellness
                    </a>
                    <a href="services.php?category=5" class="block py-2 px-6 text-sm text-gray-600 dark:text-gray-300 hover:text-seait-orange transition">
                        <i class="fas fa-bus mr-2"></i>Transportation
                    </a>
                    <a href="services.php?category=6" class="block py-2 px-6 text-sm text-gray-600 dark:text-gray-300 hover:text-seait-orange transition">
                        <i class="fas fa-desktop mr-2"></i>Virtual Learning
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Login Modal -->
<div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 animate-fade-in-modal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden relative">
        <!-- Modal Header with Gradient and Logo -->
        <div class="flex flex-col items-center justify-center p-6 border-b border-gray-200 bg-gradient-to-r from-seait-orange to-orange-400 relative">
            <img src="assets/images/seait-logo.png" alt="SEAIT Logo" class="h-12 w-12 rounded-full shadow-lg mb-2 border-4 border-white bg-white">
            <h3 class="text-xl font-bold text-white drop-shadow">Login to SEAIT</h3>
            <button onclick="closeLoginModal()" class="absolute top-4 right-4 text-white hover:text-orange-100 transition text-2xl focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <form id="loginForm" method="POST" class="space-y-5">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username or Email</label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200 shadow-sm hover:border-seait-orange"
                           placeholder="Enter your username or email" autocomplete="username">
                </div>

                <div class="relative">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200 shadow-sm hover:border-seait-orange pr-12"
                           placeholder="Enter your password" autocomplete="current-password">
                    <button type="button" tabindex="-1" onclick="togglePasswordVisibility()" class="absolute top-8 right-3 text-gray-400 hover:text-seait-orange focus:outline-none" aria-label="Show password">
                        <i id="passwordToggleIcon" class="fas fa-eye"></i>
                    </button>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="mx-3 text-xs text-gray-400">or</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                <div class="flex items-center justify-between">
                    <a href="#" class="text-xs text-seait-orange hover:underline focus:outline-none">Forgot Password?</a>
                </div>

                <div class="flex space-x-3 mt-2">
                    <button type="submit" class="flex-1 bg-seait-orange text-white py-2 px-4 rounded-lg hover:bg-orange-600 transition font-semibold shadow focus:outline-none focus:ring-2 focus:ring-seait-orange focus:ring-offset-2">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                    <button type="button" onclick="closeLoginModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300 transition font-semibold shadow focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery and jGrowl -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jgrowl/1.4.8/jquery.jgrowl.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jgrowl/1.4.8/jquery.jgrowl.min.css">

<style>
/* Custom jGrowl Error Theme */
.jGrowl-error {
    background-color: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.jGrowl-error .jGrowl-header {
    color: #dc2626;
    font-weight: 600;
    font-size: 14px;
}

.jGrowl-error .jGrowl-message {
    color: #dc2626;
    font-size: 13px;
}

.jGrowl-error .jGrowl-close {
    color: #dc2626;
}

.jGrowl-error .jGrowl-close:hover {
    color: #b91c1c;
}

/* Custom jGrowl Success Theme */
.jGrowl-success {
    background-color: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #16a34a;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.jGrowl-success .jGrowl-header {
    color: #16a34a;
    font-weight: 600;
    font-size: 14px;
}

.jGrowl-success .jGrowl-message {
    color: #16a34a;
    font-size: 13px;
}

.jGrowl-success .jGrowl-close {
    color: #16a34a;
}

.jGrowl-success .jGrowl-close:hover {
    color: #15803d;
}

/* jGrowl Container Styling */
#jGrowl {
    z-index: 9999;
}

.jGrowl-notification {
    font-family: 'Poppins', sans-serif;
}

/* Mobile Menu Styles */
#mobile-menu {
    transition: all 0.3s ease-in-out;
    max-height: 0;
    overflow: hidden;
}

#mobile-menu:not(.hidden) {
    max-height: 500px;
}

/* Ensure mobile menu is above other content */
#mobile-menu {
    position: relative;
    z-index: 50;
}

/* Mobile menu button styles */
#mobile-menu-button {
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

nav.bg-transparent, nav.bg-transparent.sticky {
    background: rgba(255,255,255,0.7) !important;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    box-shadow: none !important;
    border: none !important;
}

@keyframes fadeInModal {
    from { opacity: 0; transform: scale(0.97) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-fade-in-modal {
    animation: fadeInModal 0.4s cubic-bezier(0.4,0,0.2,1);
}
</style>

<script>
// Navbar JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Navbar JavaScript loaded');
    
    // Wait a bit to ensure DOM is fully loaded
    setTimeout(() => {
        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

    console.log('Mobile menu button:', mobileMenuButton);
    console.log('Mobile menu:', mobileMenu);

    if (mobileMenuButton && mobileMenu) {
        // Add both click and touch events for better mobile support
        const toggleMobileMenu = function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Mobile menu button clicked/touched');
            
            // Toggle the hidden class
            const isHidden = mobileMenu.classList.contains('hidden');
            if (isHidden) {
                mobileMenu.classList.remove('hidden');
                console.log('Mobile menu shown');
            } else {
                mobileMenu.classList.add('hidden');
                console.log('Mobile menu hidden');
            }
        };

        mobileMenuButton.addEventListener('click', toggleMobileMenu);
        mobileMenuButton.addEventListener('touchstart', toggleMobileMenu);

        // Close mobile menu when clicking on a link
        const mobileMenuLinks = mobileMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', function() {
                console.log('Mobile menu link clicked');
                mobileMenu.classList.add('hidden');
            });
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!mobileMenuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenu.classList.add('hidden');
            }
        });
    } else {
        console.error('Mobile menu elements not found');
    }

    // Mobile Services Dropdown functionality
    const mobileServicesToggle = document.getElementById('mobile-services-toggle');
    const mobileServicesMenu = document.getElementById('mobile-services-menu');
    const mobileServicesIcon = document.getElementById('mobile-services-icon');

    console.log('Mobile services toggle:', mobileServicesToggle);
    console.log('Mobile services menu:', mobileServicesMenu);
    console.log('Mobile services icon:', mobileServicesIcon);

    if (mobileServicesToggle && mobileServicesMenu && mobileServicesIcon) {
        mobileServicesToggle.addEventListener('click', function() {
            console.log('Mobile services toggle clicked');
            mobileServicesMenu.classList.toggle('hidden');
            mobileServicesIcon.classList.toggle('rotate-180');
        });

        // Close mobile services dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!mobileServicesToggle.contains(event.target) && !mobileServicesMenu.contains(event.target)) {
                mobileServicesMenu.classList.add('hidden');
                mobileServicesIcon.classList.remove('rotate-180');
            }
        });
    } else {
        console.error('Mobile services elements not found');
    }
    }, 100); // Small delay to ensure DOM is ready
});

// Test function for debugging mobile menu
window.testMobileMenu = function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    console.log('=== Mobile Menu Test ===');
    console.log('Button found:', !!mobileMenuButton);
    console.log('Menu found:', !!mobileMenu);
    console.log('Menu hidden:', mobileMenu ? mobileMenu.classList.contains('hidden') : 'N/A');
    console.log('Button visible:', mobileMenuButton ? window.getComputedStyle(mobileMenuButton).display !== 'none' : 'N/A');
    
    if (mobileMenuButton && mobileMenu) {
        console.log('Testing click...');
        mobileMenuButton.click();
        setTimeout(() => {
            console.log('Menu hidden after click:', mobileMenu.classList.contains('hidden'));
        }, 100);
    }
};

// Login Modal Functions - Make them global
window.openLoginModal = function() {
    const modal = document.getElementById('loginModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
};

window.closeLoginModal = function() {
    const modal = document.getElementById('loginModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
    }
};

// Password visibility toggle function
window.togglePasswordVisibility = function() {
    const passwordInput = document.getElementById('password');
    const passwordToggleIcon = document.getElementById('passwordToggleIcon');
    
    if (passwordInput && passwordToggleIcon) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            passwordToggleIcon.classList.remove('fa-eye');
            passwordToggleIcon.classList.add('fa-eye-slash');
            passwordToggleIcon.setAttribute('aria-label', 'Hide password');
        } else {
            passwordInput.type = 'password';
            passwordToggleIcon.classList.remove('fa-eye-slash');
            passwordToggleIcon.classList.add('fa-eye');
            passwordToggleIcon.setAttribute('aria-label', 'Show password');
        }
    }
};

// Simple notification function that works without jQuery
function showNotification(message, type = 'error') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
        type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' :
        type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' :
        'bg-blue-100 border border-blue-400 text-blue-700'
    }`;

    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'} mr-2"></i>
                <span class="text-sm font-medium">${message}</span>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Initialize login functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking outside
    const modal = document.getElementById('loginModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeLoginModal();
            }
        });
    }

    // Handle login form submission
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault();

            // Get form data
            const formData = new FormData(loginForm);
            const username = formData.get('username');
            const password = formData.get('password');

            // Basic validation
            if (!username || !password) {
                showNotification('Please fill in all required fields.', 'error');
                return;
            }

            // Show loading state
            const submitButton = loginForm.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
            submitButton.disabled = true;

            // Submit form via AJAX
            fetch('login_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;

                console.log('Login response:', data); // Debug log

                if (data.success) {
                    showNotification(data.message, 'success');
                    closeLoginModal();
                    console.log('Redirecting to:', data.redirect_url); // Debug log
                    setTimeout(() => {
                        // Redirect to the appropriate dashboard
                        window.location.href = data.redirect_url;
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                // Reset button state
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;

                console.error('Login error:', error);
                showNotification('An error occurred during login. Please try again.', 'error');
            });
        });
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLoginModal();
    }
});

// Initialize jGrowl if jQuery is available (for backward compatibility)
function initJGrowl() {
    if (typeof jQuery !== 'undefined' && typeof $.jGrowl !== 'undefined') {
        $.jGrowl.defaults = {
            life: 5000,
            position: 'top-right',
            sticky: false,
            theme: 'jGrowl-error',
            themeState: 'error',
            closerTemplate: '<div>[ close all ]</div>',
            beforeOpen: function(e, m, o) {
                $(e).hide().fadeIn(300);
            },
            beforeClose: function(e, m, o) {
                $(e).fadeOut(300);
            }
        };
    }
}

// Try to initialize jGrowl after a delay
setTimeout(initJGrowl, 1000);
</script>