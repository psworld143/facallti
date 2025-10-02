<?php
#Commit by Adrianne
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/database.php';
require_once 'includes/unified-error-handler.php';
require_once 'includes/functions.php';
require_once 'includes/id_encryption.php';

// Get school logo and abbreviation from database
$school_logo = get_school_logo($conn);
$school_abbreviation = get_school_abbreviation($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($school_abbreviation); ?> - Login</title>
    <!-- Favicon Configuration -->
    <?php echo generate_favicon_tags($conn); ?>
    <meta name="msapplication-TileColor" content="#FF6B35">
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
            background: linear-gradient(135deg, #FFF8F0 0%, #FED7AA 100%);
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 107, 53, 0.1);
        }

        .form-input:focus {
            border-color: #FF6B35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #FF6B35 0%, #EA580C 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #EA580C 0%, #DC2626 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 53, 0.3);
        }

        .checkbox:checked {
            background-color: #FF6B35;
            border-color: #FF6B35;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <!-- Simple Header -->
   

    <!-- Login Form -->
    <div class="w-full max-w-md mx-auto px-6">
        <div class="login-container rounded-2xl shadow-2xl p-8">
            <!-- Login Header -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-seait-orange rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-graduation-cap text-white text-3xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-seait-dark mb-2">Welcome Back</h2>
                <p class="text-gray-600">Sign in to your account</p>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-seait-orange"></i>Username
                    </label>
                    <input type="text" id="username" name="username" required
                           class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200"
                           placeholder="Enter your username">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-seait-orange"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200 pr-12"
                               placeholder="Enter your password">
                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-seait-orange transition-colors">
                            <i id="passwordToggle" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <input type="checkbox" class="checkbox h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                    <a href="#" class="text-sm text-seait-orange hover:text-orange-600 transition-colors">Forgot password?</a>
                </div>

                <button type="submit" class="btn-primary w-full py-3 px-4 rounded-lg text-white font-semibold text-lg shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
            </form>


            <!-- Footer -->
            <div class="mt-8 text-center text-sm text-gray-600">
                <p>Access FaCallTi Screen: <a href="facallti/" class="text-seait-orange hover:text-orange-600 font-medium transition-colors">FaCallTi Screen</a></p>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
            }
        }

        // Login form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const rememberMe = document.querySelector('input[type="checkbox"]').checked;
            
            // Clear any previous error messages
            clearErrorMessage();
            
            if (!username || !password) {
                showErrorMessage('Please fill in all fields');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
            submitBtn.disabled = true;
            
            // Create FormData for AJAX request
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            
            // Make AJAX request to login_ajax.php
            fetch('login_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    // Store remember me preference
                    if (rememberMe) {
                        localStorage.setItem('remembered_username', username);
                    } else {
                        localStorage.removeItem('remembered_username');
                    }
                    
                    // Show success message
                    showSuccessMessage(data.message);
                    
                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 1500);
                } else {
                    showErrorMessage(data.message || 'Login failed. Please try again.');
                }
            })
            .catch(error => {
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                console.error('Login error:', error);
                showErrorMessage('An error occurred. Please try again.');
            });
        });
        
        // Function to show error message
        function showErrorMessage(message) {
            clearErrorMessage();
            const errorDiv = document.createElement('div');
            errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + message;
            errorDiv.id = 'error-message';
            
            const form = document.getElementById('loginForm');
            form.insertBefore(errorDiv, form.firstChild);
        }
        
        // Function to show success message
        function showSuccessMessage(message) {
            clearErrorMessage();
            const successDiv = document.createElement('div');
            successDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
            successDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
            successDiv.id = 'success-message';
            
            const form = document.getElementById('loginForm');
            form.insertBefore(successDiv, form.firstChild);
        }
        
        // Function to clear error/success messages
        function clearErrorMessage() {
            const existingError = document.getElementById('error-message');
            const existingSuccess = document.getElementById('success-message');
            if (existingError) existingError.remove();
            if (existingSuccess) existingSuccess.remove();
        }
        
        // Load remembered username on page load
        document.addEventListener('DOMContentLoaded', function() {
            const rememberedUsername = localStorage.getItem('remembered_username');
            if (rememberedUsername) {
                document.getElementById('username').value = rememberedUsername;
                document.querySelector('input[type="checkbox"]').checked = true;
            }
            
            // Focus on username field if empty, otherwise on password field
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            if (usernameField.value.trim() === '') {
                usernameField.focus();
            } else {
                passwordField.focus();
            }
        });
        
        // Add Enter key support for form submission
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement && (activeElement.id === 'username' || activeElement.id === 'password')) {
                    document.getElementById('loginForm').dispatchEvent(new Event('submit'));
                }
            }
        });
    </script>
</body>
</html>