                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript for sidebar functionality -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (window.innerWidth < 1024 &&
                !sidebar.contains(event.target) &&
                !event.target.closest('[onclick*="toggleSidebar"]')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            }
        });
        
        // Initialize jGrowl
        function initJGrowl() {
            if (typeof jQuery !== 'undefined' && typeof $.jGrowl !== 'undefined') {
                $.jGrowl.defaults = {
                    life: 5000,
                    position: 'top-right',
                    sticky: false,
                    theme: 'jGrowl-error',
                    themeState: 'error',
                    closerTemplate: '<div>[ close all ]</div>',
                    beforeOpen: function(e, m, o) {
                        $(e).hide().fadeIn(300);
                    },
                    beforeClose: function(e, m, o) {
                        $(e).fadeOut(300);
                    }
                };
            }
        }
        
        // Try to initialize jGrowl after a delay
        setTimeout(initJGrowl, 1000);
    </script>
</body>
</html>
