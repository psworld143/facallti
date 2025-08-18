<!-- Animated Error Modal Component -->
<div id="errorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[70] flex items-center justify-center p-4" role="dialog" aria-labelledby="errorModalTitle" aria-describedby="errorModalMessage" aria-modal="true">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto transform transition-all duration-300 scale-95 opacity-0" id="errorModalContent">
        <!-- Modal Header with Icon -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-lg" aria-hidden="true"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800">Error</h3>
            </div>
            <button onclick="closeErrorModal()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-1 rounded-full hover:bg-gray-100" aria-label="Close error modal">
                <i class="fas fa-times text-xl" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <div class="text-center">
                <div class="mb-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-user-times text-red-600 text-2xl" aria-hidden="true"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-800 mb-2" id="errorModalTitle">Authentication Failed</h4>
                    <p class="text-gray-600 text-sm leading-relaxed" id="errorModalMessage">
                        The username or password you entered is incorrect. Please try again.
                    </p>
                </div>

                <!-- Additional Info for Login Errors -->
                <div id="errorModalHelp" class="bg-gray-50 rounded-lg p-4 mb-4 hidden">
                    <h5 class="text-sm font-medium text-gray-700 mb-2">Need Help?</h5>
                    <ul class="text-xs text-gray-600 space-y-1 text-left">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2 flex-shrink-0" aria-hidden="true"></i>
                            <span>Make sure Caps Lock is off</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2 flex-shrink-0" aria-hidden="true"></i>
                            <span>Check your username/email spelling</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2 flex-shrink-0" aria-hidden="true"></i>
                            <span>Contact IT support if you forgot your password</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex space-x-3 p-6 border-t border-gray-200">
            <button onclick="closeErrorModal()"
                    class="flex-1 bg-gray-100 text-gray-700 py-3 px-4 rounded-lg font-medium hover:bg-gray-200 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Try Again
            </button>
            <button onclick="closeErrorModal()"
                    class="flex-1 bg-seait-orange text-white py-3 px-4 rounded-lg font-medium hover:bg-orange-600 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:ring-offset-2">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Error Modal Functions - Make them global
window.showErrorModal = function(title = 'Error', message = 'An error occurred. Please try again.', showHelp = false) {
    const modal = document.getElementById('errorModal');
    const modalContent = document.getElementById('errorModalContent');
    const modalTitle = document.getElementById('errorModalTitle');
    const modalMessage = document.getElementById('errorModalMessage');
    const modalHelp = document.getElementById('errorModalHelp');

    if (modal && modalContent && modalTitle && modalMessage) {
        // Close login modal if it's open
        const loginModal = document.getElementById('loginModal');
        if (loginModal && !loginModal.classList.contains('hidden')) {
            closeLoginModal();
        }

        // Store the currently focused element
        modal._previousActiveElement = document.activeElement;

        // Set content
        modalTitle.textContent = title;
        modalMessage.textContent = message;

        // Show/hide help section
        if (modalHelp) {
            if (showHelp) {
                modalHelp.classList.remove('hidden');
            } else {
                modalHelp.classList.add('hidden');
            }
        }

        // Show modal with animation
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling

        // Trigger animation
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);

        // Focus trap for accessibility
        const focusableElements = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const firstFocusableElement = focusableElements[0];
        const lastFocusableElement = focusableElements[focusableElements.length - 1];

        // Focus the first focusable element
        if (firstFocusableElement) {
            firstFocusableElement.focus();
        }

        // Handle tab key for focus trap
        const handleTabKey = (e) => {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusableElement) {
                        e.preventDefault();
                        lastFocusableElement.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusableElement) {
                        e.preventDefault();
                        firstFocusableElement.focus();
                    }
                }
            }
        };

        modal.addEventListener('keydown', handleTabKey);

        // Store the event listener for cleanup
        modal._tabKeyHandler = handleTabKey;

        // Auto-close after 8 seconds for login errors
        if (title.toLowerCase().includes('authentication') || title.toLowerCase().includes('login')) {
            setTimeout(() => {
                closeErrorModal();
            }, 8000);
        }
    }
};

window.closeErrorModal = function() {
    const modal = document.getElementById('errorModal');
    const modalContent = document.getElementById('errorModalContent');

    if (modal && modalContent) {
        // Remove focus trap event listener
        if (modal._tabKeyHandler) {
            modal.removeEventListener('keydown', modal._tabKeyHandler);
            delete modal._tabKeyHandler;
        }

        // Trigger close animation
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');

        // Hide modal after animation
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = ''; // Restore scrolling

            // Restore focus to the element that was focused before the modal opened
            if (modal._previousActiveElement) {
                modal._previousActiveElement.focus();
                delete modal._previousActiveElement;
            }
        }, 300);
    }
};

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('errorModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeErrorModal();
            }
        });
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeErrorModal();
    }
});

// Specific function for login errors
window.showLoginError = function() {
    showErrorModal(
        'Authentication Failed',
        'The username or password you entered is incorrect. Please check your credentials and try again.',
        true
    );

    // Add shake animation to the modal content
    const modalContent = document.getElementById('errorModalContent');
    if (modalContent) {
        modalContent.classList.add('shake-animation');
        setTimeout(() => {
            modalContent.classList.remove('shake-animation');
        }, 600);
    }

    // Add pulse animation to the error icon
    const errorIcon = document.querySelector('#errorModal .fa-user-times');
    if (errorIcon) {
        errorIcon.parentElement.classList.add('pulse-red');
        setTimeout(() => {
            errorIcon.parentElement.classList.remove('pulse-red');
        }, 2000);
    }
};

// Specific function for validation errors
window.showValidationError = function(message) {
    showErrorModal(
        'Validation Error',
        message,
        false
    );
};

// Specific function for general errors
window.showGeneralError = function(message) {
    showErrorModal(
        'Error',
        message,
        false
    );
};
</script>

<style>
/* Additional animations for error modal */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.shake-animation {
    animation: shake 0.6s ease-in-out;
}

/* Pulse animation for the error icon */
@keyframes pulse-red {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
    }
}

.pulse-red {
    animation: pulse-red 2s infinite;
}

/* Fade in animation for help section */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in-up {
    animation: fadeInUp 0.5s ease-out;
}
</style>