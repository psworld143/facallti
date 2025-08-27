// Login page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Add form validation
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', validateLoginForm);
    }
    
    // Add input focus effects
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', addFocusEffect);
        input.addEventListener('blur', removeFocusEffect);
    });
    
    // Add demo account quick fill
    addDemoAccountQuickFill();
});

// Validate login form
function validateLoginForm(e) {
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    let isValid = true;
    
    // Clear previous errors
    clearFieldErrors();
    
    // Validate username
    if (!username.value.trim()) {
        showFieldError(username, 'Username is required');
        isValid = false;
    }
    
    // Validate password
    if (!password.value.trim()) {
        showFieldError(password, 'Password is required');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        showNotification('Please fill in all required fields', 'warning');
    }
}

// Show field error
function showFieldError(field, message) {
    field.classList.add('border-red-500');
    field.classList.remove('border-gray-300');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'text-red-500 text-sm mt-1';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

// Clear field errors
function clearFieldErrors() {
    const errorMessages = document.querySelectorAll('.text-red-500');
    errorMessages.forEach(error => error.remove());
    
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.classList.remove('border-red-500');
        input.classList.add('border-gray-300');
    });
}

// Add focus effect
function addFocusEffect(e) {
    const field = e.target;
    field.parentNode.classList.add('ring-2', 'ring-primary', 'ring-opacity-50');
}

// Remove focus effect
function removeFocusEffect(e) {
    const field = e.target;
    field.parentNode.classList.remove('ring-2', 'ring-primary', 'ring-opacity-50');
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;
    
    // Set colors based on type
    switch(type) {
        case 'success':
            notification.className += ' bg-green-500 text-white';
            break;
        case 'error':
            notification.className += ' bg-red-500 text-white';
            break;
        case 'warning':
            notification.className += ' bg-yellow-500 text-black';
            break;
        default:
            notification.className += ' bg-blue-500 text-white';
    }
    
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${getNotificationIcon(type)} mr-3"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-75">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Get notification icon
function getNotificationIcon(type) {
    switch(type) {
        case 'success': return 'check-circle';
        case 'error': return 'exclamation-triangle';
        case 'warning': return 'exclamation-circle';
        default: return 'info-circle';
    }
}

// Add demo account quick fill
function addDemoAccountQuickFill() {
    const demoAccounts = document.querySelectorAll('.bg-gray-50');
    
    demoAccounts.forEach(account => {
        account.addEventListener('click', function() {
            const text = this.textContent;
            const credentials = text.match(/(\w+)\s*\/\s*(\w+)/);
            
            if (credentials) {
                const username = credentials[1];
                const password = credentials[2];
                
                document.getElementById('username').value = username;
                document.getElementById('password').value = password;
                
                // Add visual feedback
                this.classList.add('ring-2', 'ring-green-500');
                setTimeout(() => {
                    this.classList.remove('ring-2', 'ring-green-500');
                }, 1000);
                
                showNotification(`Demo account filled: ${username}`, 'success');
            }
        });
        
        // Add hover effect
        account.classList.add('cursor-pointer', 'hover:bg-gray-100', 'transition-colors');
    });
}

// Add loading state to form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging in...';
            }
        });
    }
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+Enter to submit form
    if (e.ctrlKey && e.key === 'Enter') {
        const form = document.querySelector('form');
        if (form) {
            form.submit();
        }
    }
    
    // Escape to clear form
    if (e.key === 'Escape') {
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => input.value = '');
        clearFieldErrors();
    }
});

// Add password visibility toggle
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('password');
    if (passwordField) {
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        
        toggleBtn.addEventListener('click', function() {
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        const passwordContainer = passwordField.parentNode;
        passwordContainer.classList.add('relative');
        passwordContainer.appendChild(toggleBtn);
    }
});
