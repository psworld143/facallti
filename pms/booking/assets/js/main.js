// Main JavaScript file for Hotel PMS

// Sidebar functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar.classList.contains('sidebar-open')) {
        // Close sidebar
        sidebar.classList.remove('sidebar-open');
        overlay.classList.add('hidden');
    } else {
        // Open sidebar
        sidebar.classList.add('sidebar-open');
        overlay.classList.remove('hidden');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.remove('sidebar-open');
    overlay.classList.add('hidden');
}

function toggleSubmenu(menuId) {
    const submenu = document.getElementById('submenu-' + menuId);
    const chevron = document.getElementById('chevron-' + menuId);
    
    if (submenu.classList.contains('hidden')) {
        submenu.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        submenu.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
    }
}

// Utility functions
const Utils = {
    // Show notification
    showNotification: function(message, type = 'info') {
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
                <i class="fas fa-${this.getNotificationIcon(type)} mr-3"></i>
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
    },
    
    // Get notification icon
    getNotificationIcon: function(type) {
        switch(type) {
            case 'success': return 'check-circle';
            case 'error': return 'exclamation-triangle';
            case 'warning': return 'exclamation-circle';
            default: return 'info-circle';
        }
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    },
    
    // Format date
    formatDate: function(date, format = 'short') {
        const d = new Date(date);
        if (format === 'short') {
            return d.toLocaleDateString();
        } else if (format === 'long') {
            return d.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        return d.toLocaleDateString();
    },
    
    // Format datetime
    formatDateTime: function(date) {
        const d = new Date(date);
        return d.toLocaleString();
    },
    
    // Confirm action
    confirmAction: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },
    
    // Show loading spinner
    showLoading: function(element) {
        element.innerHTML = '<div class="flex items-center justify-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    },
    
    // Hide loading spinner
    hideLoading: function(element, content) {
        element.innerHTML = content;
    },
    
    // Debounce function
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Validate email
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    // Validate phone
    validatePhone: function(phone) {
        const re = /^[\+]?[1-9][\d]{0,15}$/;
        return re.test(phone.replace(/[\s\-\(\)]/g, ''));
    }
};

// Initialize sidebar functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileToggle = document.getElementById('mobile-sidebar-toggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleSidebar);
    }
    
    // Close sidebar when clicking on overlay
    const overlay = document.getElementById('sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar when clicking on a link (mobile)
    const sidebarLinks = document.querySelectorAll('#sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 1024) {
                closeSidebar();
            }
        });
    });
});

// AJAX helper
const Ajax = {
    // GET request
    get: function(url, callback) {
        fetch(url)
            .then(response => response.json())
            .then(data => callback(null, data))
            .catch(error => callback(error));
    },
    
    // POST request
    post: function(url, data, callback) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => callback(null, data))
        .catch(error => callback(error));
    },
    
    // Form POST request
    postForm: function(url, formData, callback) {
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => callback(null, data))
        .catch(error => callback(error));
    }
};

// Form validation
const FormValidator = {
    // Validate required fields
    validateRequired: function(fields) {
        let isValid = true;
        fields.forEach(field => {
            const element = document.getElementById(field);
            if (!element.value.trim()) {
                this.showFieldError(element, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(element);
            }
        });
        return isValid;
    },
    
    // Show field error
    showFieldError: function(element, message) {
        element.classList.add('border-red-500');
        element.classList.remove('border-gray-300');
        
        // Remove existing error message
        const existingError = element.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Add error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error text-red-500 text-sm mt-1';
        errorDiv.textContent = message;
        element.parentNode.appendChild(errorDiv);
    },
    
    // Clear field error
    clearFieldError: function(element) {
        element.classList.remove('border-red-500');
        element.classList.add('border-gray-300');
        
        const errorDiv = element.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
};

// Table helper
const TableHelper = {
    // Create table row
    createRow: function(data, columns) {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        
        columns.forEach(column => {
            const cell = document.createElement('td');
            cell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-900';
            
            if (column.render) {
                cell.innerHTML = column.render(data[column.key], data);
            } else {
                cell.textContent = data[column.key] || '';
            }
            
            row.appendChild(cell);
        });
        
        return row;
    },
    
    // Create status badge
    createStatusBadge: function(status, text) {
        const badgeClasses = {
            'available': 'bg-green-100 text-green-800',
            'occupied': 'bg-red-100 text-red-800',
            'reserved': 'bg-yellow-100 text-yellow-800',
            'maintenance': 'bg-blue-100 text-blue-800',
            'dirty': 'bg-red-100 text-red-800',
            'clean': 'bg-green-100 text-green-800'
        };
        
        return `<span class="px-2 py-1 text-xs font-medium rounded-full ${badgeClasses[status] || 'bg-gray-100 text-gray-800'}">${text}</span>`;
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add global error handler
    window.addEventListener('error', function(e) {
        console.error('Global error:', e.error);
        Utils.showNotification('An error occurred. Please try again.', 'error');
    });
    
    // Add form validation listeners
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            const fields = Array.from(requiredFields).map(field => field.id);
            
            if (!FormValidator.validateRequired(fields)) {
                e.preventDefault();
                Utils.showNotification('Please fill in all required fields.', 'warning');
            }
        });
    });
    
    // Add auto-save functionality for forms
    const autoSaveForms = document.querySelectorAll('form[data-autosave]');
    autoSaveForms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        const saveUrl = form.getAttribute('data-autosave');
        
        inputs.forEach(input => {
            input.addEventListener('change', Utils.debounce(function() {
                const formData = new FormData(form);
                Ajax.postForm(saveUrl, formData, function(error, response) {
                    if (!error && response.success) {
                        Utils.showNotification('Changes saved automatically.', 'success');
                    }
                });
            }, 1000));
        });
    });
});

// Export for use in other modules
window.HotelPMS = {
    Utils,
    Ajax,
    FormValidator,
    TableHelper
};
