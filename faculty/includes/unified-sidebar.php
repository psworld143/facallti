<?php
// Unified Sidebar for Faculty Portal
// This file provides a consistent sidebar across all faculty pages
// Usage: include this file and set $sidebar_context before including

// Default context if not set
if (!isset($sidebar_context)) {
    $sidebar_context = 'main'; // 'main' for regular faculty pages, 'lms' for class pages
}

// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity duration-300 ease-in-out opacity-0 pointer-events-none" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 bg-seait-dark z-50 lg:relative lg:translate-x-0 lg:z-auto transform transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0">
    <!-- Sidebar Header -->
    <div class="sidebar-header flex items-center justify-center p-4 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700">
        <div class="flex items-center transform transition-transform duration-200 hover:scale-105">
            <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-8 w-auto mr-2 transition-all duration-200 hover:rotate-12">
            <span class="text-white font-semibold">
                <?php echo $sidebar_context === 'lms' ? 'SEAIT LMS' : 'Faculty Portal'; ?>
            </span>
        </div>
    </div>

    <!-- Navigation Menu - Scrollable Content -->
    <div class="sidebar-content">
        <!-- User Profile Section -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg mx-3 transform transition-all duration-300 hover:bg-gray-700 hover:scale-105 hover:shadow-lg">
            <div class="flex items-center">
                <div class="h-12 w-12 rounded-full bg-seait-orange flex items-center justify-center mr-3 transition-all duration-300 hover:bg-orange-500 hover:scale-110 hover:shadow-md">
                    <span class="text-white font-semibold text-lg"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></span>
                </div>
                <div class="flex-1">
                    <p class="text-white font-semibold text-sm"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                    <p class="text-gray-400 text-xs">Teacher</p>
                    <div class="flex items-center mt-1">
                        <div class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                        <span class="text-green-400 text-xs">Online</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($sidebar_context === 'lms' && isset($class_data)): ?>
        <!-- Class Info Section (LMS Only) -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg mx-3 transform transition-all duration-300 hover:bg-gray-700 hover:scale-105 hover:shadow-lg">
            <h3 class="text-white font-semibold text-sm mb-2">Current Class</h3>
            <p class="text-gray-300 text-xs font-medium"><?php echo htmlspecialchars($class_data['subject_code']); ?></p>
            <p class="text-white text-sm"><?php echo htmlspecialchars($class_data['subject_title']); ?></p>
            <p class="text-gray-300 text-xs mt-1">Section <?php echo htmlspecialchars($class_data['section']); ?></p>
            <p class="text-gray-300 text-xs">Join Code: <?php echo htmlspecialchars($class_data['join_code']); ?></p>
        </div>
        <?php endif; ?>

        <div class="space-y-6">
            <?php if ($sidebar_context === 'main'): ?>
            <!-- Main Faculty Portal Navigation -->

            <!-- Dashboard Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.1s;">
                <a href="dashboard.php" class="flex items-center <?php echo $current_page === 'dashboard.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                    <i class="fas fa-tachometer-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Dashboard
                </a>
            </div>

            <!-- Class Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Class Management</h3>
                <div class="space-y-1">
                    <a href="class-management.php" class="flex items-center <?php echo $current_page === 'class-management.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-list mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>All Classes
                    </a>

                    <a href="quizzes.php" class="flex items-center <?php echo $current_page === 'quizzes.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-question-circle mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Quizzes
                    </a>

                    <a href="lessons.php" class="flex items-center <?php echo in_array($current_page, ['lessons.php', 'create-lesson.php', 'view-lesson.php']) ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-book mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Lessons
                    </a>

                    <a href="class-analytics.php" class="flex items-center <?php echo $current_page === 'class-analytics.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-chart-bar mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Class Analytics
                    </a>

                    <a href="class-settings.php" class="flex items-center <?php echo $current_page === 'class-settings.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-cog mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Settings
                    </a>
                </div>
            </div>

            <!-- Evaluation Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Evaluations</h3>
                <div class="space-y-1">
                    <a href="evaluations.php" class="flex items-center <?php echo $current_page === 'evaluations.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-list mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>All Evaluations
                    </a>

                    <a href="peer-evaluations.php" class="flex items-center <?php echo $current_page === 'peer-evaluations.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-users mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Peer to Peer
                    </a>

                    <a href="evaluation-results.php" class="flex items-center <?php echo $current_page === 'evaluation-results.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-chart-bar mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>My Results
                    </a>
                </div>
            </div>

            <!-- Training & Development Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.35s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Training & Development</h3>
                <div class="space-y-1">
                    <a href="my-trainings.php" class="flex items-center <?php echo in_array($current_page, ['my-trainings.php', 'view-training.php']) ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-graduation-cap mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>My Trainings
                    </a>
                </div>
            </div>

            <!-- Communication Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.45s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Communication</h3>
                <div class="space-y-1">
                    <a href="announcements.php" class="flex items-center <?php echo $current_page === 'announcements.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-bullhorn mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Announcements
                    </a>

                    <a href="calendar.php" class="flex items-center <?php echo $current_page === 'calendar.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-calendar-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Calendar
                    </a>
                </div>
            </div>

            <!-- Reports & Analytics Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.5s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Reports & Analytics</h3>
                <div class="space-y-1">
                    <a href="reports.php" class="flex items-center <?php echo $current_page === 'reports.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-chart-line mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Reports
                    </a>
                </div>
            </div>

            <!-- Profile Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.55s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Account</h3>
                <div class="space-y-1">
                    <a href="profile.php" class="flex items-center <?php echo $current_page === 'profile.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-user mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Profile
                    </a>
                </div>
            </div>

            <?php else: ?>
            <!-- LMS Class Navigation -->

            <!-- Dashboard Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.1s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Class Overview</h3>
                <div class="space-y-1">
                    <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_dashboard.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-tachometer-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Dashboard
                    </a>
                </div>
            </div>

            <!-- Student Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Student Management</h3>
                <div class="space-y-1">
                    <a href="class_students.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_students.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-users mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Students
                    </a>
                </div>
            </div>

            <!-- Content Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Content Management</h3>
                <div class="space-y-1">
                    <a href="class_announcements.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_announcements.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-bullhorn mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Announcements
                    </a>

                    <a href="class_materials.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_materials.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-book mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Learning Materials
                    </a>

                    <a href="class_syllabus.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_syllabus.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-list-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Syllabus
                    </a>

                    <a href="class_assignments.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_assignments.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-tasks mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Assignments
                    </a>

                    <a href="class_quizzes.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_quizzes.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-question-circle mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Quizzes
                    </a>

                    <a href="class_discussions.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_discussions.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-comments mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Discussions
                    </a>
                </div>
            </div>

            <!-- Assessment & Analytics Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Assessment & Analytics</h3>
                <div class="space-y-1">
                    <a href="class_grades.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_grades.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-chart-line mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Grades & Progress
                    </a>

                    <a href="class_evaluations.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_evaluations.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-clipboard-check mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Student Evaluations
                    </a>
                </div>
            </div>

            <!-- Tools Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.5s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Tools</h3>
                <div class="space-y-1">
                    <a href="class_calendar.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo $current_page === 'class_calendar.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-calendar-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Class Calendar
                    </a>
                </div>
            </div>

            <!-- Navigation Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.6s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Navigation</h3>
                <div class="space-y-1">
                    <a href="class-management.php" class="flex items-center text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-arrow-left mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Back to Classes
                    </a>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer p-4 border-t border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700">
        <a href="logout.php" class="flex items-center bg-red-600 text-white hover:bg-red-700 px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-lg">
            <i class="fas fa-sign-out-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Logout
        </a>
    </div>
</div>

<style>
/* Custom animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeInUp {
    animation: fadeInUp 0.6s ease-out forwards;
    opacity: 0;
}

/* Sidebar open/close animations */
.sidebar.open {
    transform: translateX(0);
}

.sidebar-overlay.open {
    opacity: 1;
    pointer-events: auto;
}

/* Smooth transitions for all interactive elements */
.sidebar a {
    position: relative;
    overflow: hidden;
}

.sidebar a::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
}

.sidebar a:hover::before {
    left: 100%;
}

/* Active state animations */
.sidebar a.bg-seait-orange {
    animation: activePulse 2s infinite;
}

@keyframes activePulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.7);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(249, 115, 22, 0);
    }
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar && overlay) {
        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        } else {
            sidebar.classList.add('open');
            overlay.classList.add('open');
        }
    }
}

// Initialize sidebar behavior
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarLinks = document.querySelectorAll('#sidebar a:not([href^="#"])'); // Exclude anchor links
    const sidebarContent = document.querySelector('.sidebar-content');

    // Store scroll position in sessionStorage
    function saveScrollPosition() {
        if (sidebarContent) {
            sessionStorage.setItem('sidebarScrollPosition', sidebarContent.scrollTop);
        }
    }

    // Restore scroll position from sessionStorage
    function restoreScrollPosition() {
        if (sidebarContent) {
            const savedPosition = sessionStorage.getItem('sidebarScrollPosition');
            if (savedPosition !== null) {
                setTimeout(() => {
                    sidebarContent.scrollTop = parseInt(savedPosition);
                }, 100);
            }
        }
    }

    // Save scroll position when scrolling
    if (sidebarContent) {
        sidebarContent.addEventListener('scroll', function() {
            saveScrollPosition();
        });
    }

    // Restore scroll position on page load
    restoreScrollPosition();

    // Ensure sidebar is in correct state on load
    if (sidebar) {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
        }
    }

    // Handle link clicks - NO MOVEMENT
    sidebarLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            // Save current scroll position before navigation
            saveScrollPosition();

            // Prevent any default behavior that might cause movement
            e.preventDefault();

            // Get the href and navigate immediately without any delays or animations
            const href = link.getAttribute('href');
            if (href) {
                // Navigate immediately - no delays, no animations, no sidebar movement
                window.location.href = href;
            }
        });
    });

    // Handle overlay clicks
    if (overlay) {
        overlay.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.innerWidth < 1024) {
                toggleSidebar();
            }
        });
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (window.innerWidth >= 1024) {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }
});

// Prevent unwanted sidebar interactions on desktop
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            // Prevent event bubbling on desktop
            if (window.innerWidth >= 1024) {
                e.stopPropagation();
            }
        });
    }
});

// Prevent any zooming or scaling issues
document.addEventListener('DOMContentLoaded', function() {
    // Ensure viewport meta tag is properly set
    const viewport = document.querySelector('meta[name="viewport"]');
    if (!viewport) {
        const meta = document.createElement('meta');
        meta.name = 'viewport';
        meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
        document.head.appendChild(meta);
    } else {
        viewport.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
    }
});
</script>