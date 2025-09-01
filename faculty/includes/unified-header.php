<?php
// Unified Header for Faculty Portal
// This file provides a consistent header across all faculty pages
// Usage: include this file and set $sidebar_context before including

// Default context if not set
if (!isset($sidebar_context)) {
    $sidebar_context = 'main'; // 'main' for regular faculty pages, 'lms' for class pages
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Faculty Portal' : 'Faculty Portal'; ?></title>
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
                        'seait-light': '#FFF8F0'
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
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(180deg, #2C3E50 0%, #34495E 100%);
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

        /* Sidebar scrollable content */
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem;
        }

        .sidebar-content::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        .sidebar-content::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Sidebar header and footer fixed */
        .sidebar-header,
        .sidebar-footer {
            flex-shrink: 0;
        }

        /* Prevent horizontal overflow */
        body, html {
            overflow-x: hidden;
            max-width: 100vw;
        }

        /* Ensure main content doesn't overflow */
        .flex-1 {
            min-width: 0;
        }

        /* Enhanced Lesson Card Styling */
        .lesson-card {
            background: linear-gradient(135deg, #eef2ff 0%, #f9fafb 100%) !important;
            border-left: 4px solid #6366f1 !important;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.15) !important;
            position: relative;
            overflow: visible !important;
            word-wrap: break-word;
            word-break: break-word;
        }

        .lesson-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            z-index: 1;
        }

        .lesson-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.25) !important;
        }

        .lesson-card .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            word-wrap: break-word;
            word-break: break-word;
        }

        .lesson-card h3 {
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .lesson-card p {
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .lesson-icon {
            background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4) !important;
            transform: scale(1.05);
            flex-shrink: 0;
        }

        .lesson-badge {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe) !important;
            border: 1px solid #a5b4fc !important;
            box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2) !important;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .lesson-status-published {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0) !important;
            border: 1px solid #86efac !important;
            color: #166534 !important;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .lesson-status-draft {
            background: linear-gradient(135deg, #fef3c7, #fde68a) !important;
            border: 1px solid #fbbf24 !important;
            color: #92400e !important;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .lesson-action-btn {
            background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
            font-weight: 600;
            letter-spacing: 0.025em;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .lesson-action-btn:hover {
            background: linear-gradient(135deg, #4f46e5, #3730a3) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4) !important;
        }

        @keyframes lessonFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .lesson-card {
            animation: lessonFadeIn 0.4s ease-out;
        }

        /* Responsive lesson card adjustments */
        @media (max-width: 768px) {
            .lesson-card {
                min-height: auto;
                padding: 0.75rem !important;
            }

            .lesson-card h3 {
                font-size: 0.875rem !important;
                line-height: 1.25rem !important;
            }

            .lesson-card .text-xs {
                font-size: 0.75rem !important;
            }

            .lesson-card .flex.space-x-2 {
                flex-wrap: wrap;
                gap: 0.25rem;
            }

            .lesson-card .flex.space-x-2 > * {
                margin-right: 0;
                margin-bottom: 0.25rem;
            }
        }

        @media (max-width: 640px) {
            .lesson-card {
                padding: 0.5rem !important;
            }

            .lesson-card .w-10.h-10 {
                width: 2rem !important;
                height: 2rem !important;
                margin-right: 0.5rem !important;
            }

            .lesson-card .text-sm {
                font-size: 0.75rem !important;
            }

            .lesson-card .inline-flex.items-center.px-3.py-1\.5 {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.75rem !important;
            }
        }

        /* Sidebar item styles for LMS context */
        .sidebar-item {
            transition: all 0.2s ease;
        }
        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        .sidebar-item.active {
            background-color: rgba(255, 107, 53, 0.2);
            border-left: 4px solid #FF6B35;
        }

        /* Mobile responsiveness improvements */
        @media (max-width: 768px) {
            .px-4 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .px-6 {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }

            .px-8 {
                padding-left: 2rem;
                padding-right: 2rem;
            }
        }

        @media (max-width: 640px) {
            .px-4 {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .px-6 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .px-8 {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .px-4 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .px-6 {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .px-8 {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
        }
        .submenu.open {
            max-height: 400px;
        }
        .submenu a {
            margin-bottom: 2px;
        }
        .submenu a:last-child {
            margin-bottom: 0;
        }

        /* Quiz-specific styles */
        .overflow-x-auto {
            overflow-x: auto;
        }

        .min-w-full {
            min-width: 100%;
        }

        .whitespace-nowrap {
            white-space: nowrap;
        }

        .flex.space-x-2 > * {
            margin-right: 0.5rem;
        }

        .flex.space-x-2 > *:last-child {
            margin-right: 0;
        }

        .inline-flex.items-center.px-2.py-1 {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
        }

        .text-sm {
            line-height: 1.4;
        }

        .text-xs {
            line-height: 1.3;
        }

        .px-6.py-3 {
            padding: 0.75rem 1.5rem;
        }

        .px-6.py-4 {
            padding: 1rem 1.5rem;
        }

        .hover\:bg-gray-50:hover {
            background-color: #f9fafb;
        }

        .text-blue-600:hover,
        .text-green-600:hover,
        .text-red-600:hover {
            text-decoration: none;
        }

        .fas {
            display: inline-block;
            width: 1em;
            text-align: center;
        }

        /* Prose styling for lesson content */
        .prose {
            color: #374151;
            max-width: none;
        }

        .prose h1 {
            color: #111827;
            font-weight: 800;
            font-size: 2.25em;
            margin-top: 0;
            margin-bottom: 0.8888889em;
            line-height: 1.1111111;
        }

        .prose h2 {
            color: #111827;
            font-weight: 700;
            font-size: 1.5em;
            margin-top: 2em;
            margin-bottom: 1em;
            line-height: 1.3333333;
        }

        .prose h3 {
            color: #111827;
            font-weight: 600;
            font-size: 1.25em;
            margin-top: 1.6em;
            margin-bottom: 0.6em;
            line-height: 1.6;
        }

        .prose h4 {
            color: #111827;
            font-weight: 600;
            margin-top: 1.5em;
            margin-bottom: 0.5em;
            line-height: 1.5;
        }

        .prose p {
            margin-top: 1.25em;
            margin-bottom: 1.25em;
            line-height: 1.75;
        }

        .prose ul {
            margin-top: 1.25em;
            margin-bottom: 1.25em;
            padding-left: 1.625em;
        }

        .prose ol {
            margin-top: 1.25em;
            margin-bottom: 1.25em;
            padding-left: 1.625em;
        }

        .prose li {
            margin-top: 0.5em;
            margin-bottom: 0.5em;
            line-height: 1.75;
        }

        .prose li > ul {
            margin-top: 0.75em;
            margin-bottom: 0.75em;
        }

        .prose li > ol {
            margin-top: 0.75em;
            margin-bottom: 0.75em;
        }

        .prose strong {
            color: #111827;
            font-weight: 600;
        }

        .prose a {
            color: #2563eb;
            text-decoration: underline;
            font-weight: 500;
        }

        .prose a:hover {
            color: #1d4ed8;
        }

        .prose blockquote {
            font-weight: 500;
            font-style: italic;
            color: #111827;
            border-left-width: 0.25rem;
            border-left-color: #e5e7eb;
            quotes: "\201C""\201D""\2018""\2019";
            margin-top: 1.6em;
            margin-bottom: 1.6em;
            padding-left: 1em;
        }

        .prose blockquote p:first-of-type::before {
            content: open-quote;
        }

        .prose blockquote p:last-of-type::after {
            content: close-quote;
        }

        .prose code {
            color: #111827;
            font-weight: 600;
            font-size: 0.875em;
        }

        .prose code::before {
            content: "`";
        }

        .prose code::after {
            content: "`";
        }

        .prose pre {
            color: #e5e7eb;
            background-color: #1f2937;
            overflow-x: auto;
            font-weight: 400;
            font-size: 0.875em;
            line-height: 1.7142857;
            margin-top: 1.7142857em;
            margin-bottom: 1.7142857em;
            border-radius: 0.375rem;
            padding-top: 0.8571429em;
            padding-right: 1.1428571em;
            padding-bottom: 0.8571429em;
            padding-left: 1.1428571em;
        }

        .prose pre code {
            background-color: transparent;
            border-width: 0;
            border-radius: 0;
            padding: 0;
            font-weight: 400;
            color: inherit;
            font-size: inherit;
            font-family: inherit;
            line-height: inherit;
        }

        .prose pre code::before {
            content: none;
        }

        .prose pre code::after {
            content: none;
        }

        .prose table {
            width: 100%;
            table-layout: auto;
            text-align: left;
            margin-top: 2em;
            margin-bottom: 2em;
            font-size: 0.875em;
            line-height: 1.7142857;
        }

        .prose thead {
            color: #111827;
            font-weight: 600;
            border-bottom-width: 1px;
            border-bottom-color: #d1d5db;
        }

        .prose thead th {
            vertical-align: bottom;
            padding-right: 0.5714286em;
            padding-bottom: 0.5714286em;
            padding-left: 0.5714286em;
        }

        .prose tbody tr {
            border-bottom-width: 1px;
            border-bottom-color: #e5e7eb;
        }

        .prose tbody tr:nth-child(2n) {
            background-color: #f9fafb;
        }

        .prose tbody td {
            vertical-align: baseline;
            padding: 0.5714286em;
        }

        .prose figure {
            margin-top: 2em;
            margin-bottom: 2em;
        }

        .prose figure > * {
            margin-top: 0;
            margin-bottom: 0;
        }

        .prose figcaption {
            color: #6b7280;
            font-size: 0.875em;
            line-height: 1.4285714;
            margin-top: 0.8571429em;
        }

        .prose hr {
            border-color: #e5e7eb;
            border-top-width: 1px;
            margin-top: 3em;
            margin-bottom: 3em;
        }

        /* Grid layout improvements for lesson cards */
        .grid.grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-3.xl\:grid-cols-4 {
            gap: 1rem !important;
        }

        .grid.grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-3.xl\:grid-cols-4 > * {
            min-width: 0;
            max-width: 100%;
        }

        /* Ensure lesson cards don't overflow their containers */
        .lesson-card {
            max-width: 100%;
            box-sizing: border-box;
        }

        .lesson-card > * {
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Fix for long lesson titles */
        .lesson-card h3.truncate {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Ensure flex containers don't overflow */
        .lesson-card .flex {
            min-width: 0;
            max-width: 100%;
        }

        .lesson-card .flex > * {
            min-width: 0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'unified-sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex justify-between items-center py-4 px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center">
                        <!-- Mobile Sidebar Toggle -->
                        <button onclick="toggleSidebar()" class="lg:hidden mr-3 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-bars text-xl"></i>
                        </button>

                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-seait-dark">
                                <?php
                                if ($sidebar_context === 'lms' && isset($class_data)) {
                                    echo htmlspecialchars($class_data['subject_title']);
                                } else {
                                    echo 'Faculty Portal';
                                }
                                ?>
                            </h1>
                            <p class="text-xs sm:text-sm text-gray-600">
                                <?php
                                if ($sidebar_context === 'lms' && isset($class_data)) {
                                    echo 'Section ' . htmlspecialchars($class_data['section']) . ' - ' . (isset($page_title) ? $page_title : 'Class Management');
                                } else {
                                    echo 'Teacher Management System';
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="hidden sm:block text-right">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                            <p class="text-sm text-gray-500">Teacher</p>
                        </div>
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-seait-orange flex items-center justify-center">
                            <span class="text-white text-sm sm:text-base font-medium"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 py-4 sm:py-6 px-4 sm:px-6 lg:px-8 overflow-auto">
                <div class="px-0 sm:px-0">