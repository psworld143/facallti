// Dark Mode JavaScript
class DarkMode {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.systemPreference = window.matchMedia('(prefers-color-scheme: dark)');
        this.init();
    }

    init() {
        // Set initial theme
        this.setTheme(this.theme);
        
        // Add event listeners
        this.addEventListeners();
        
        // Watch for system preference changes
        this.systemPreference.addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    setTheme(theme) {
        this.theme = theme;
        
        // Update data attribute
        document.documentElement.setAttribute('data-theme', theme);
        
        // Add/remove dark-mode class
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            // Force text color updates
            this.updateTextColors();
        } else {
            document.body.classList.remove('dark-mode');
        }
        
        // Update toggle button
        this.updateToggleButton();
        
        // Save to localStorage
        localStorage.setItem('theme', theme);
        
        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
    }

    updateTextColors() {
        // Force update text colors for dark mode
        const textElements = document.querySelectorAll('p, h1, h2, h3, h4, h5, h6, span, div, li, label, strong, em, b');
        textElements.forEach(element => {
            if (element.classList.contains('text-gray-600') || 
                element.classList.contains('text-gray-700') || 
                element.classList.contains('text-gray-800') || 
                element.classList.contains('text-gray-900')) {
                // Force reflow to ensure styles are applied
                element.style.color = '';
                element.offsetHeight;
            }
        });
    }

    toggleTheme() {
        const newTheme = this.theme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    }

    updateToggleButton() {
        const toggleBtn = document.querySelector('.theme-toggle');
        if (toggleBtn) {
            const sunIcon = toggleBtn.querySelector('.fa-sun');
            const moonIcon = toggleBtn.querySelector('.fa-moon');
            
            if (this.theme === 'dark') {
                if (sunIcon) sunIcon.style.display = 'inline-block';
                if (moonIcon) moonIcon.style.display = 'none';
            } else {
                if (sunIcon) sunIcon.style.display = 'none';
                if (moonIcon) moonIcon.style.display = 'inline-block';
            }
        }
    }

    addEventListeners() {
        // Theme toggle button
        document.addEventListener('click', (e) => {
            if (e.target.closest('.theme-toggle')) {
                e.preventDefault();
                this.toggleTheme();
            }
        });

        // Keyboard shortcut (Ctrl/Cmd + J)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'j') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }

    // Get current theme
    getCurrentTheme() {
        return this.theme;
    }

    // Check if dark mode is active
    isDarkMode() {
        return this.theme === 'dark';
    }
}

// Initialize dark mode when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.darkMode = new DarkMode();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DarkMode;
} 