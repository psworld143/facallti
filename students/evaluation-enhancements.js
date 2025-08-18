// Enhanced Evaluation UI JavaScript

class EvaluationUI {
    constructor() {
        this.autoSaveTimer = null;
        this.progressData = {
            total: 0,
            answered: 0
        };
        this.form = null;
        this.init();
    }

    init() {
        this.form = document.getElementById('evaluationForm');
        if (this.form) {
            this.setupEventListeners();
            this.updateProgress();
            this.setupRatingStars();
        }
    }

    setupEventListeners() {
        // Add event listeners for all form inputs
        const inputs = this.form.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', () => this.updateProgress());
            if (input.type === 'textarea') {
                input.addEventListener('blur', () => this.autoSave());
                input.addEventListener('input', () => this.debounceAutoSave());
            }
        });

        // Prevent form submission on Enter key in textareas
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.tagName === 'TEXTAREA' && e.ctrlKey) {
                e.preventDefault();
                this.form.submit();
            }
        });

        // Add smooth scrolling for category navigation
        this.setupCategoryNavigation();
    }

    setupRatingStars() {
        document.querySelectorAll('.rating-star').forEach(star => {
            star.addEventListener('click', (e) => {
                const radio = star.querySelector('input[type="radio"]');
                if (radio) {
                    // Remove selected class from all stars in this group
                    const group = star.closest('.rating-stars');
                    group.querySelectorAll('.rating-star').forEach(s => s.classList.remove('selected'));
                    
                    // Add selected class to clicked star
                    star.classList.add('selected');
                    radio.checked = true;
                    this.updateProgress();
                }
            });
        });
    }

    setupCategoryNavigation() {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                const categoryName = e.target.textContent.trim();
                this.scrollToCategory(categoryName);
                
                // Update active nav item
                navItems.forEach(nav => nav.classList.remove('active'));
                e.target.classList.add('active');
            });
        });
    }

    updateProgress() {
        if (!this.form) return;

        const inputs = this.form.querySelectorAll('input[type="radio"]:checked, textarea');
        let answered = 0;
        
        inputs.forEach(input => {
            if (input.type === 'radio' || (input.type === 'textarea' && input.value.trim() !== '')) {
                answered++;
            }
        });
        
        this.progressData.answered = answered;
        const percentage = this.progressData.total > 0 ? Math.round((answered / this.progressData.total) * 100) : 0;
        
        // Update progress bar
        const progressBar = document.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = percentage + '%';
        }
        
        // Update progress text
        const progressText = document.querySelector('.progress-bar')?.parentElement?.nextElementSibling?.querySelector('span');
        if (progressText) {
            progressText.textContent = percentage + '% Complete';
        }
        
        // Update question cards
        this.updateQuestionCards();
        
        // Update progress counter
        const progressCounter = document.querySelector('.progress-counter');
        if (progressCounter) {
            progressCounter.textContent = `${answered} of ${this.progressData.total} questions answered`;
        }
    }

    updateQuestionCards() {
        const questionCards = document.querySelectorAll('.question-card');
        questionCards.forEach(card => {
            const inputs = card.querySelectorAll('input[type="radio"]:checked, textarea');
            let isAnswered = false;
            
            inputs.forEach(input => {
                if (input.type === 'radio' || (input.type === 'textarea' && input.value.trim() !== '')) {
                    isAnswered = true;
                }
            });
            
            if (isAnswered) {
                card.classList.add('answered');
                this.addAnsweredIndicator(card);
            } else {
                card.classList.remove('answered');
                this.removeAnsweredIndicator(card);
            }
        });
    }

    addAnsweredIndicator(card) {
        if (!card.querySelector('.answered-indicator')) {
            const indicator = document.createElement('span');
            indicator.className = 'answered-indicator text-green-500 text-sm flex items-center absolute top-4 right-4';
            indicator.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Answered';
            card.style.position = 'relative';
            card.appendChild(indicator);
        }
    }

    removeAnsweredIndicator(card) {
        const indicator = card.querySelector('.answered-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    debounceAutoSave() {
        clearTimeout(this.autoSaveTimer);
        this.autoSaveTimer = setTimeout(() => this.autoSave(), 1000);
    }

    autoSave() {
        // Show auto-save indicator
        this.showAutoSaveIndicator();
        
        // Here you could implement actual auto-save functionality
        // For now, we'll just show the indicator
        console.log('Auto-saving...');
    }

    showAutoSaveIndicator() {
        const indicator = document.getElementById('autoSaveIndicator');
        if (indicator) {
            indicator.classList.add('show');
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }
    }

    saveDraft() {
        if (this.form) {
            const actionInput = this.form.querySelector('input[name="action"]');
            if (actionInput) {
                actionInput.value = 'save_draft';
            }
            this.form.submit();
        }
    }

    scrollToCategory(categoryName) {
        const element = document.getElementById('category-' + categoryName);
        if (element) {
            element.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start',
                inline: 'nearest'
            });
        }
    }

    // Form validation
    validateForm() {
        const requiredFields = this.form.querySelectorAll('[required]');
        let isValid = true;
        const errors = [];

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                errors.push(field.name || 'Required field');
                this.highlightError(field);
            } else {
                this.removeErrorHighlight(field);
            }
        });

        if (!isValid) {
            this.showValidationErrors(errors);
        }

        return isValid;
    }

    highlightError(field) {
        field.classList.add('border-red-500', 'bg-red-50');
        field.style.borderWidth = '2px';
    }

    removeErrorHighlight(field) {
        field.classList.remove('border-red-500', 'bg-red-50');
        field.style.borderWidth = '';
    }

    showValidationErrors(errors) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4';
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <div>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mt-2 list-disc list-inside">
                        ${errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;

        const form = document.getElementById('evaluationForm');
        form.insertBefore(errorDiv, form.firstChild);

        // Remove error message after 5 seconds
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }

    // Enhanced textarea functionality
    setupEnhancedTextareas() {
        const textareas = document.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            // Add character counter
            this.addCharacterCounter(textarea);
            
            // Auto-resize
            textarea.addEventListener('input', () => {
                this.autoResizeTextarea(textarea);
            });
        });
    }

    addCharacterCounter(textarea) {
        const counter = document.createElement('div');
        counter.className = 'text-xs text-gray-500 mt-1 text-right';
        counter.textContent = `0 characters`;
        
        textarea.parentNode.appendChild(counter);
        
        textarea.addEventListener('input', () => {
            const length = textarea.value.length;
            counter.textContent = `${length} characters`;
            
            if (length > 500) {
                counter.classList.add('text-red-500');
            } else {
                counter.classList.remove('text-red-500');
            }
        });
    }

    autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    // Loading states
    showLoading(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="loading-spinner mr-2"></span>Saving...';
        button.disabled = true;
        return originalText;
    }

    hideLoading(button, originalText) {
        button.innerHTML = originalText;
        button.disabled = false;
    }

    // Success animation
    showSuccessAnimation() {
        const successDiv = document.createElement('div');
        successDiv.className = 'fixed inset-0 bg-green-500 bg-opacity-75 flex items-center justify-center z-50';
        successDiv.innerHTML = `
            <div class="bg-white rounded-lg p-8 text-center">
                <i class="fas fa-check-circle text-green-500 text-6xl mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Success!</h3>
                <p class="text-gray-600">Your evaluation has been saved successfully.</p>
            </div>
        `;
        
        document.body.appendChild(successDiv);
        
        setTimeout(() => {
            successDiv.remove();
        }, 2000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const evaluationUI = new EvaluationUI();
    
    // Make it globally available
    window.evaluationUI = evaluationUI;
    
    // Setup enhanced textareas
    evaluationUI.setupEnhancedTextareas();
    
    // Set total questions count
    const totalQuestions = document.querySelectorAll('.question-card').length;
    evaluationUI.progressData.total = totalQuestions;
});

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EvaluationUI;
} 