        </main>
    </div>

    <!-- JavaScript for navigation functionality -->
    <script>
        // Sidebar functionality
        function toggleSidebar() {
            const mobileToggle = document.getElementById('mobile-sidebar-toggle');
            
            if (mobileToggle) {
                mobileToggle.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    mobileToggle.style.transform = 'scale(1)';
                }, 150);
            }
            
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (window.innerWidth < 1024) {
                const isOpen = sidebar.getAttribute('data-mobile-open') === 'true';
                
                if (isOpen) {
                    sidebar.setAttribute('data-mobile-open', 'false');
                    sidebar.style.transform = 'translateX(-100%)';
                    if (overlay) {
                        overlay.classList.add('hidden');
                    }
                } else {
                    sidebar.setAttribute('data-mobile-open', 'true');
                    sidebar.style.transform = 'translateX(0)';
                    if (overlay) {
                        overlay.classList.remove('hidden');
                    }
                }
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.setAttribute('data-mobile-open', 'false');
            sidebar.style.transform = 'translateX(-100%)';
            if (overlay) {
                overlay.classList.add('hidden');
            }
        }

        // User dropdown functionality
        function toggleUserDropdown() {
            const dropdown = document.getElementById('user-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('user-dropdown');
            const userButton = event.target.closest('button');
            
            if (!userButton || !userButton.onclick) {
                dropdown.classList.add('hidden');
            }
        });

        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            
            const dateElement = document.getElementById('current-date');
            const timeElement = document.getElementById('current-time');
            
            if (dateElement) {
                dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
            }
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
            }
        }
        
        // Initialize date/time and update every second
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                const sidebar = document.getElementById('sidebar');
                sidebar.style.transform = 'translateX(0)';
            }
        });
    </script>
</body>
</html>
