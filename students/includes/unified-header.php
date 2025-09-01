<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . ($sidebar_context === 'lms' ? 'SEAIT LMS' : 'Student Portal') : ($sidebar_context === 'lms' ? 'SEAIT LMS' : 'Student Portal'); ?></title>
    <link rel="icon" type="image/png" href="../../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../../assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0',
                        'seait-orange-light': '#FF8A65',
                        'seait-dark-light': '#34495E'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .sidebar-overlay {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }
        .sidebar-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        @media (min-width: 1024px) {
            .sidebar {
                transform: translateX(0);
            }
        }
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
        }
        .submenu.open {
            max-height: 200px;
        }
        .nav-item {
            position: relative;
            transition: all 0.2s ease-in-out;
        }
        .nav-item:hover {
            transform: translateX(4px);
        }
        .nav-item.active {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8A65 100%);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #FF6B35;
            border-radius: 0 2px 2px 0;
        }
        .profile-avatar {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8A65 100%);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }
        .section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.1) 50%, transparent 100%);
        }
        .menu-category {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

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
        .sidebar a.active {
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

        /* Custom styles for class cards */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .class-card {
            transition: all 0.3s ease;
            position: relative;
        }

        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .header-gradient {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
        }

        /* Card header overlay effect */
        .card-header-overlay {
            background: linear-gradient(45deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.2) 100%);
        }

        /* Teacher avatar enhancement */
        .teacher-avatar {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
            box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3);
        }

        /* Action button enhancements */
        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Status badge enhancements */
        .status-badge {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Evaluation-specific styles */
        .progress-bar {
            background: linear-gradient(90deg, #FF6B35 0%, #FF8C42 100%);
            transition: width 0.5s ease-in-out;
        }

        .question-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .question-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .question-card.answered {
            border-left-color: #10B981;
            background-color: #F0FDF4;
        }

        .rating-stars {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .rating-star {
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 8px 12px;
            border-radius: 8px;
            border: 2px solid #E5E7EB;
            background: white;
            min-width: 60px;
            text-align: center;
        }

        .rating-star:hover {
            border-color: #FF6B35;
            background-color: #FFF8F0;
        }

        .rating-star.selected {
            border-color: #FF6B35;
            background-color: #FF6B35;
            color: white;
        }

        .auto-save-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .auto-save-indicator.show {
            opacity: 1;
        }

        .category-nav {
            position: sticky;
            top: 0;
            z-index: 10;
            background: white;
            border-bottom: 1px solid #E5E7EB;
        }

        .nav-item {
            transition: all 0.3s ease;
        }

        .nav-item.active {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
            color: white;
            transform: scale(1.05);
        }

        /* Mobile-specific improvements for conduct evaluation */
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        /* Ensure text wrapping for long category names */
        .category-nav .nav-item .truncate {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            .category-nav .nav-item .truncate {
                max-width: 80px;
            }

            .rating-stars {
                gap: 4px;
            }

            .rating-star {
                padding: 6px 8px;
                min-width: 50px;
                font-size: 0.75rem;
            }

            .question-card {
                padding: 0.75rem;
            }

            .question-card .flex.items-start.justify-between {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'unified-sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-lg border-b border-gray-200">
                <div class="flex justify-between items-center py-4 px-6 lg:px-8">
                    <div class="flex items-center">
                        <!-- Mobile Sidebar Toggle -->
                        <button onclick="toggleSidebar()" class="lg:hidden mr-4 text-gray-600 hover:text-gray-900 transition-colors">
                            <i class="fas fa-bars text-xl"></i>
                        </button>

                        <div>
                            <h1 class="text-xl lg:text-2xl font-bold text-seait-dark">
                                <?php
                                if ($sidebar_context === 'lms') {
                                    echo htmlspecialchars($class_data['subject_title'] ?? 'Class Dashboard');
                                } else {
                                    echo 'Student Portal';
                                }
                                ?>
                            </h1>
                            <p class="text-sm text-gray-600">
                                <?php
                                if ($sidebar_context === 'lms') {
                                    echo 'Learning Management System';
                                } else {
                                    echo 'Class Management & Evaluation System';
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="hidden sm:block text-right">
                            <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                            <p class="text-sm text-gray-500">Student</p>
                        </div>
                        <div class="profile-avatar h-10 w-10 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 py-6 px-6 lg:px-8 overflow-auto">
                <div class="px-0">