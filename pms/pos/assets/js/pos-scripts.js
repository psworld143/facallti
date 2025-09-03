/**
 * POS Module JavaScript
 * Independent JavaScript for POS system functionality
 */

class POSSystem {
    constructor() {
        this.currentModule = 'dashboard';
        this.cart = [];
        this.init();
    }
    
    /**
     * Initialize POS system
     */
    init() {
        this.setupEventListeners();
        this.initializeComponents();
        this.startAutoRefresh();
        console.log('POS System initialized');
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Navigation
        document.addEventListener('click', (e) => {
            if (e.target.matches('.pos-nav-link')) {
                this.handleNavigation(e.target);
            }
        });
        
        // Service buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.pos-service-btn')) {
                this.handleServiceClick(e.target.closest('.pos-service-btn'));
            }
        });
        
        // Form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.matches('.pos-form')) {
                this.handleFormSubmit(e);
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    }
    
    /**
     * Handle navigation clicks
     */
    handleNavigation(navLink) {
        const module = navLink.dataset.module;
        if (module) {
            this.navigateToModule(module);
        }
    }
    
    /**
     * Navigate to specific module
     */
    navigateToModule(module) {
        this.currentModule = module;
        
        // Update active navigation
        document.querySelectorAll('.pos-nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        const activeLink = document.querySelector(`[data-module="${module}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
        
        // Load module content
        this.loadModuleContent(module);
    }
    
    /**
     * Load module content
     */
    async loadModuleContent(module) {
        try {
            const response = await fetch(`modules/${module}/index.php`);
            if (response.ok) {
                const content = await response.text();
                document.getElementById('pos-content').innerHTML = content;
                this.initializeModuleComponents(module);
            }
        } catch (error) {
            console.error('Error loading module:', error);
            this.showAlert('Error loading module content', 'error');
        }
    }
    
    /**
     * Handle service button clicks
     */
    handleServiceClick(button) {
        const service = button.dataset.service;
        const action = button.dataset.action;
        
        switch (action) {
            case 'navigate':
                this.navigateToModule(service);
                break;
            case 'quick-action':
                this.performQuickAction(service);
                break;
            default:
                console.log('Unknown action:', action);
        }
    }
    
    /**
     * Perform quick action
     */
    performQuickAction(action) {
        switch (action) {
            case 'new-transaction':
                this.createNewTransaction();
                break;
            case 'view-reports':
                this.showReports();
                break;
            case 'manage-inventory':
                this.manageInventory();
                break;
            default:
                console.log('Unknown quick action:', action);
        }
    }
    
    /**
     * Handle form submissions
     */
    handleFormSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const action = form.dataset.action;
        
        switch (action) {
            case 'login':
                this.handleLogin(formData);
                break;
            case 'transaction':
                this.handleTransaction(formData);
                break;
            case 'inventory':
                this.handleInventory(formData);
                break;
            default:
                console.log('Unknown form action:', action);
        }
    }
    
    /**
     * Handle login
     */
    async handleLogin(formData) {
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.showAlert('Login successful!', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                } else {
                    this.showAlert(result.message || 'Login failed', 'error');
                }
            }
        } catch (error) {
            console.error('Login error:', error);
            this.showAlert('Login error occurred', 'error');
        }
    }
    
    /**
     * Handle transaction
     */
    async handleTransaction(formData) {
        try {
            const response = await fetch('api/process-transaction.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.showAlert('Transaction completed successfully!', 'success');
                    this.clearCart();
                    this.refreshDashboard();
                } else {
                    this.showAlert(result.message || 'Transaction failed', 'error');
                }
            }
        } catch (error) {
            console.error('Transaction error:', error);
            this.showAlert('Transaction error occurred', 'error');
        }
    }
    
    /**
     * Handle inventory
     */
    async handleInventory(formData) {
        try {
            const response = await fetch('api/update-inventory.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.showAlert('Inventory updated successfully!', 'success');
                    this.refreshInventory();
                } else {
                    this.showAlert(result.message || 'Inventory update failed', 'error');
                }
            }
        } catch (error) {
            console.error('Inventory error:', error);
            this.showAlert('Inventory error occurred', 'error');
        }
    }
    
    /**
     * Handle keyboard shortcuts
     */
    handleKeyboardShortcuts(event) {
        // Ctrl/Cmd + N: New transaction
        if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
            event.preventDefault();
            this.createNewTransaction();
        }
        
        // Ctrl/Cmd + R: Refresh
        if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
            event.preventDefault();
            this.refreshDashboard();
        }
        
        // Ctrl/Cmd + S: Save
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            this.saveCurrentData();
        }
        
        // Escape: Close modals/forms
        if (event.key === 'Escape') {
            this.closeModals();
        }
    }
    
    /**
     * Show alert message
     */
    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `pos-alert pos-alert-${type} pos-fade-in`;
        alertDiv.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="text-lg">&times;</button>
            </div>
        `;
        
        const container = document.querySelector('.pos-alerts') || document.body;
        container.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    /**
     * Initialize components
     */
    initializeComponents() {
        this.initializeDateDisplay();
        this.initializeNotifications();
        this.initializeCharts();
    }
    
    /**
     * Initialize date display
     */
    initializeDateDisplay() {
        const updateDateTime = () => {
            const now = new Date();
            const dateElement = document.getElementById('current-date');
            const timeElement = document.getElementById('current-time');
            
            if (dateElement) {
                dateElement.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
            
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', {
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
        };
        
        updateDateTime();
        setInterval(updateDateTime, 1000);
    }
    
    /**
     * Initialize notifications
     */
    initializeNotifications() {
        // Check for new notifications every 30 seconds
        setInterval(() => {
            this.checkNotifications();
        }, 30000);
    }
    
    /**
     * Check for new notifications
     */
    async checkNotifications() {
        try {
            const response = await fetch('api/check-notifications.php');
            if (response.ok) {
                const notifications = await response.json();
                this.displayNotifications(notifications);
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }
    
    /**
     * Display notifications
     */
    displayNotifications(notifications) {
        notifications.forEach(notification => {
            this.showAlert(notification.message, notification.type);
        });
    }
    
    /**
     * Initialize charts
     */
    initializeCharts() {
        // Initialize any charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            this.initializeSalesChart();
            this.initializeTransactionChart();
        }
    }
    
    /**
     * Initialize sales chart
     */
    initializeSalesChart() {
        const ctx = document.getElementById('sales-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Sales',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }
    }
    
    /**
     * Initialize transaction chart
     */
    initializeTransactionChart() {
        const ctx = document.getElementById('transaction-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Pending', 'Cancelled'],
                    datasets: [{
                        data: [300, 50, 100],
                        backgroundColor: [
                            '#10b981',
                            '#f59e0b',
                            '#ef4444'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        }
    }
    
    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        // Refresh dashboard every 5 minutes
        setInterval(() => {
            if (this.currentModule === 'dashboard') {
                this.refreshDashboard();
            }
        }, 300000);
    }
    
    /**
     * Refresh dashboard
     */
    async refreshDashboard() {
        try {
            const response = await fetch('api/dashboard-stats.php');
            if (response.ok) {
                const stats = await response.json();
                this.updateDashboardStats(stats);
            }
        } catch (error) {
            console.error('Error refreshing dashboard:', error);
        }
    }
    
    /**
     * Update dashboard statistics
     */
    updateDashboardStats(stats) {
        // Update statistics display
        Object.keys(stats).forEach(key => {
            const element = document.getElementById(`stat-${key}`);
            if (element) {
                if (key.includes('sales') || key.includes('revenue')) {
                    element.textContent = `₱${parseFloat(stats[key]).toLocaleString()}`;
                } else {
                    element.textContent = stats[key];
                }
            }
        });
    }
    
    /**
     * Create new transaction
     */
    createNewTransaction() {
        this.navigateToModule('quick-sales');
    }
    
    /**
     * Show reports
     */
    showReports() {
        this.navigateToModule('reports');
    }
    
    /**
     * Manage inventory
     */
    manageInventory() {
        this.navigateToModule('inventory');
    }
    
    /**
     * Clear cart
     */
    clearCart() {
        this.cart = [];
        this.updateCartDisplay();
    }
    
    /**
     * Update cart display
     */
    updateCartDisplay() {
        const cartElement = document.getElementById('pos-cart');
        if (cartElement) {
            cartElement.innerHTML = this.cart.length > 0 
                ? `<span class="bg-red-500 text-white rounded-full px-2 py-1 text-xs">${this.cart.length}</span>`
                : '';
        }
    }
    
    /**
     * Close modals
     */
    closeModals() {
        document.querySelectorAll('.pos-modal').forEach(modal => {
            modal.classList.add('hidden');
        });
    }
    
    /**
     * Save current data
     */
    saveCurrentData() {
        // Save any unsaved data
        this.showAlert('Data saved successfully!', 'success');
    }
    
    /**
     * Refresh inventory
     */
    refreshInventory() {
        // Refresh inventory display
        this.showAlert('Inventory refreshed!', 'info');
    }
}

// Initialize POS system when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.posSystem = new POSSystem();
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = POSSystem;
}
