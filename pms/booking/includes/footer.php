        </main>
    </div>

    <!-- JavaScript for navigation functionality -->
            <script>

        
                // Sidebar functionality
        function toggleSidebar() {
            // Add visual feedback to the button that was clicked
            const mobileToggle = document.getElementById('mobile-sidebar-toggle');
            
            if (mobileToggle) {
                mobileToggle.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    mobileToggle.style.transform = 'scale(1)';
                }, 150);
            }
            
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            // Only mobile behavior - desktop sidebar stays fixed
            if (window.innerWidth < 1024) {
                // Use data attribute to track state
                const isOpen = sidebar.getAttribute('data-mobile-open') === 'true';
                
                if (isOpen) {
                    // Close sidebar (mobile)
                    sidebar.setAttribute('data-mobile-open', 'false');
                    sidebar.style.transform = 'translateX(-100%)';
                    if (overlay) {
                        overlay.classList.add('hidden');
                    }
                } else {
                    // Open sidebar (mobile)
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

        // Submenu functionality
        function toggleSubmenu(menuId) {
            const submenu = document.getElementById('submenu-' + menuId);
            const chevron = document.getElementById('chevron-' + menuId);
            
            // Check if any submenu item is currently active
            const activeSubmenuItem = submenu.querySelector('a.text-blue-600');
            
            if (submenu.classList.contains('hidden')) {
                // Open submenu
                submenu.classList.remove('hidden');
                chevron.style.transform = 'rotate(180deg)';
            } else {
                // Only close submenu if no active item exists
                if (!activeSubmenuItem) {
                    submenu.classList.add('hidden');
                    chevron.style.transform = 'rotate(0deg)';
                }
            }
        }

        // Training submenu functionality
        function toggleTrainingSubmenu(menuId) {
            const submenu = document.getElementById('submenu-' + menuId);
            const chevron = document.getElementById('chevron-' + menuId);
            
            // Check if any submenu item is currently active
            const activeSubmenuItem = submenu.querySelector('a.active, a.text-blue-600');
            
            if (submenu.classList.contains('hidden')) {
                // Open submenu
                submenu.classList.remove('hidden');
                chevron.classList.add('rotate-90');
            } else {
                // Only close submenu if no active item exists
                if (!activeSubmenuItem) {
                    submenu.classList.add('hidden');
                    chevron.classList.remove('rotate-90');
                }
            }
        }

        // User dropdown functionality
        function toggleUserDropdown() {
            const dropdown = document.getElementById('user-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Notifications dropdown functionality
        function toggleNotifications() {
            const dropdown = document.getElementById('notifications-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize sidebar state
            const sidebar = document.getElementById('sidebar');
            

            
            // Initialize sidebar based on screen size
            function initializeSidebar() {
                if (sidebar) {
                    if (window.innerWidth >= 1024) { // Desktop
                        // Ensure desktop sidebar is always expanded
                        sidebar.setAttribute('data-collapsed', 'false');
                        sidebar.setAttribute('data-mobile-open', 'false');
                        sidebar.style.transform = 'translateX(0)';
                        
                        // Show all sidebar text on desktop
                        document.querySelectorAll('.sidebar-text').forEach(el => {
                            el.style.display = 'block';
                        });
                    } else { // Mobile
                        // Ensure mobile sidebar is hidden by default
                        sidebar.setAttribute('data-mobile-open', 'false');
                        sidebar.style.transform = 'translateX(-100%)';
                    }
                }
            }
            
            // Initialize on load
            initializeSidebar();
            // Force-close on mobile in case any prior scripts/styles left it open
            (function ensureMobileClosed() {
                const overlay = document.getElementById('sidebar-overlay');
                if (sidebar && window.innerWidth < 1024) {
                    sidebar.classList.remove('sidebar-open');
                    sidebar.setAttribute('data-mobile-open', 'false');
                    sidebar.style.transform = 'translateX(-100%)';
                    if (overlay) overlay.classList.add('hidden');
                }
            })();
            
            // Auto-expand submenus with active items
            document.querySelectorAll('#sidebar ul[id^="submenu-"]').forEach(submenu => {
                const activeItem = submenu.querySelector('a.text-blue-600, a.active');
                if (activeItem) {
                    // Show the submenu
                    submenu.classList.remove('hidden');
                    
                    // Rotate the chevron
                    const menuId = submenu.id.replace('submenu-', '');
                    const chevron = document.getElementById('chevron-' + menuId);
                    if (chevron) {
                        chevron.style.transform = 'rotate(180deg)';
                    }
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                const overlay = document.getElementById('sidebar-overlay');
                if (window.innerWidth >= 1024) {
                    // Desktop: close mobile overlay if open
                    if (overlay) {
                        overlay.classList.add('hidden');
                    }
                    initializeSidebar();
                } else {
                    // Mobile: ensure sidebar is hidden
                    if (sidebar) {
                        sidebar.classList.remove('sidebar-open');
                        sidebar.setAttribute('data-mobile-open', 'false');
                        sidebar.style.transform = 'translateX(-100%)';
                        if (overlay) overlay.classList.add('hidden');
                    }
                }
            });
            
            // Mobile sidebar toggle
            const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
            if (mobileSidebarToggle) {
                // Remove inline onclick and use addEventListener
                mobileSidebarToggle.removeAttribute('onclick');
                
                // Add event listener
                mobileSidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
                
                // Add touchstart for mobile devices
                mobileSidebarToggle.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            // User menu toggle
            const userMenuToggle = document.getElementById('user-menu-toggle');
            if (userMenuToggle) {
                userMenuToggle.addEventListener('click', toggleUserDropdown);
            }

            // Notifications toggle
            const notificationsToggle = document.getElementById('notifications-toggle');
            if (notificationsToggle) {
                notificationsToggle.addEventListener('click', toggleNotifications);
            }

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                const userDropdown = document.getElementById('user-dropdown');
                const notificationsDropdown = document.getElementById('notifications-dropdown');
                const userMenuToggle = document.getElementById('user-menu-toggle');
                const notificationsToggle = document.getElementById('notifications-toggle');

                // Close user dropdown
                if (userDropdown && !userMenuToggle.contains(event.target) && !userDropdown.contains(event.target)) {
                    userDropdown.classList.add('hidden');
                }

                // Close notifications dropdown
                if (notificationsDropdown && !notificationsToggle.contains(event.target) && !notificationsDropdown.contains(event.target)) {
                    notificationsDropdown.classList.add('hidden');
                }
            });

            // Load notifications
            loadNotifications();
            

        });

        // Load notifications
        function loadNotifications() {
            // Get the current path and construct the correct API path
            const currentPath = window.location.pathname;
            const apiPath = currentPath.includes('/modules/') ? '../../api/get-notifications.php' : '../api/get-notifications.php';
            fetch(apiPath)
                .then(response => response.json())
                .then(data => {
                    const notificationsList = document.getElementById('notifications-list');
                    const notificationBadge = document.getElementById('notification-badge');
                    
                    if (data.notifications && data.notifications.length > 0) {
                        notificationBadge.textContent = data.notifications.length;
                        notificationBadge.classList.remove('hidden');
                        
                        notificationsList.innerHTML = data.notifications.map(notification => `
                            <div class="p-3 border-b border-gray-100 hover:bg-gray-50">
                                <div class="flex items-start">
                                    <div class="w-2 h-2 bg-${notification.type === 'error' ? 'red' : notification.type === 'warning' ? 'yellow' : 'blue'}-500 rounded-full mt-2 mr-3"></div>
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-gray-800">${notification.title}</div>
                                        <div class="text-xs text-gray-600">${notification.message}</div>
                                        <div class="text-xs text-gray-400 mt-1">${notification.created_at}</div>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        notificationsList.innerHTML = '<div class="p-4 text-center text-gray-500">No new notifications</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                });
        }

        // Update current time
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString();
            }
            if (dateElement) {
                dateElement.textContent = now.toLocaleDateString();
            }
        }

        // Update time every second
        setInterval(updateTime, 1000);
        updateTime(); // Initial call
    </script>

    <?php if (isset($additional_js)): ?>
        <?php echo $additional_js; ?>
    <?php endif; ?>
</body>
</html>
