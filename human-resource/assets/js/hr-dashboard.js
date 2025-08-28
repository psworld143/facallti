/**
 * Human Resource Dashboard JavaScript
 */

class HRDashboard {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeTooltips();
    }

    bindEvents() {
        // Quick action links
        document.querySelectorAll('.hr-quick-action').forEach(action => {
            action.addEventListener('click', (e) => {
                this.handleQuickAction(e);
            });
        });

        // Search functionality
        const searchInput = document.querySelector('#faculty-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });
        }

        // Filter functionality
        const filterForm = document.querySelector('#faculty-filters');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFilter();
            });
        }
    }

    handleQuickAction(e) {
        const action = e.currentTarget.dataset.action;
        this.showLoading(e.currentTarget);
        
        setTimeout(() => {
            window.location.href = e.currentTarget.href;
        }, 300);
    }

    handleSearch(query) {
        if (query.length < 2) {
            this.clearSearchResults();
            return;
        }
        this.performSearch(query);
    }

    async performSearch(query) {
        try {
            const response = await fetch(`../api/search-faculty.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success) {
                this.displaySearchResults(data.results);
            } else {
                this.showToast('Search failed: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showToast('Search failed. Please try again.', 'error');
        }
    }

    displaySearchResults(results) {
        const container = document.querySelector('#search-results');
        if (!container) return;

        container.innerHTML = '';

        if (results.length === 0) {
            container.innerHTML = '<p class="text-gray-500">No results found</p>';
            return;
        }

        results.forEach(faculty => {
            const card = this.createFacultyCard(faculty);
            container.appendChild(card);
        });

        container.style.display = 'block';
    }

    createFacultyCard(faculty) {
        const card = document.createElement('div');
        card.className = 'hr-faculty-card';
        card.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="hr-faculty-avatar">
                        ${faculty.first_name.charAt(0)}${faculty.last_name.charAt(0)}
                    </div>
                    <div class="ml-3">
                        <p class="font-medium text-gray-900">${faculty.first_name} ${faculty.last_name}</p>
                        <p class="text-sm text-gray-500">${faculty.position} - ${faculty.department}</p>
                    </div>
                </div>
                <a href="view-faculty.php?id=${faculty.id}" class="hr-action-link">
                    <i class="fas fa-eye"></i> View
                </a>
            </div>
        `;
        return card;
    }

    clearSearchResults() {
        const container = document.querySelector('#search-results');
        if (container) {
            container.style.display = 'none';
        }
    }

    handleFilter() {
        const form = document.querySelector('#faculty-filters');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);

        this.showLoading(form.querySelector('button[type="submit"]'));
        window.location.href = `manage-faculty.php?${params.toString()}`;
    }

    showLoading(element) {
        if (!element) return;
        
        const originalContent = element.innerHTML;
        element.innerHTML = '<span class="inline-block w-5 h-5 border-2 border-gray-300 border-t-seait-orange rounded-full animate-spin"></span>';
        element.disabled = true;

        setTimeout(() => {
            element.innerHTML = originalContent;
            element.disabled = false;
        }, 2000);
    }

    showToast(message, type = 'info') {
        const existingToasts = document.querySelectorAll('.fixed.top-5.right-5');
        existingToasts.forEach(toast => toast.remove());

        const bgColor = type === 'success' ? 'bg-hr-success' : type === 'error' ? 'bg-hr-danger' : type === 'warning' ? 'bg-hr-warning' : 'bg-hr-accent';
        
        const toast = document.createElement('div');
        toast.className = `fixed top-5 right-5 p-4 rounded-lg text-white font-medium z-50 transform translate-x-full transition-transform duration-300 ${bgColor}`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${this.getToastIcon(type)} mr-2"></i>
                <span>${message}</span>
                <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 100);

        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 5000);
    }

    getToastIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    initializeTooltips() {
        if (typeof tippy !== 'undefined') {
            tippy('[data-tippy-content]', {
                placement: 'top',
                arrow: true,
                theme: 'light'
            });
        }
    }
}

// Utility functions
const HRUtils = {
    formatDate(date) {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    },

    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    },

    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
};

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new HRDashboard();
});

// Export for global access
window.HRDashboard = HRDashboard;
window.HRUtils = HRUtils;
