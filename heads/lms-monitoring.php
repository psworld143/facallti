<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'LMS Monitoring';

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">LMS Monitoring</h1>
            <p class="text-gray-600">Monitor teachers' Learning Management System activities</p>
        </div>
        <div class="flex space-x-3">
            <a href="dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Coming Soon Content -->
<div class="w-full">
    <!-- Coming Soon Card -->
    <div class="bg-white rounded-lg shadow-sm p-8 text-center">
        <div class="mb-6">
            <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-chalkboard-teacher text-4xl text-blue-600"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Coming Soon!</h2>
            <p class="text-lg text-gray-600">LMS Monitoring feature is under development</p>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-600 mt-1 mr-3 text-lg"></i>
                <div class="text-left">
                    <h3 class="font-semibold text-blue-800 mb-2">What's Coming:</h3>
                    <ul class="text-blue-700 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-blue-600"></i>
                            Monitor syllabus updates and modifications by teachers
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-blue-600"></i>
                            Track lesson creation and content updates
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-blue-600"></i>
                            View quiz assignments and assessment activities
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-blue-600"></i>
                            Monitor student engagement and participation
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-blue-600"></i>
                            Generate LMS activity reports and analytics
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-blue-600"></i>
                            Receive notifications for important LMS updates
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-start">
                <i class="fas fa-chart-line text-green-600 mt-1 mr-3 text-lg"></i>
                <div class="text-left">
                    <h3 class="font-semibold text-green-800 mb-2">Monitoring Features:</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-green-700">
                        <div>
                            <h4 class="font-medium mb-2">Content Monitoring</h4>
                            <ul class="text-sm space-y-1">
                                <li>• Syllabus version tracking</li>
                                <li>• Lesson update notifications</li>
                                <li>• Assignment submission monitoring</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-medium mb-2">Activity Analytics</h4>
                            <ul class="text-sm space-y-1">
                                <li>• Teacher activity dashboards</li>
                                <li>• Student engagement metrics</li>
                                <li>• Course completion tracking</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-8">
            <div class="inline-flex items-center bg-gray-100 text-gray-700 px-4 py-2 rounded-full">
                <i class="fas fa-tools mr-2"></i>
                <span>Development in Progress</span>
            </div>
        </div>
    </div>
</div>

<!-- Progress Section -->
<div class="w-full mt-8">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Development Progress</h3>
        <div class="space-y-4">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">LMS Integration API</span>
                    <span class="text-sm text-gray-500">85%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: 85%"></div>
                </div>
            </div>
            
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Monitoring Dashboard</span>
                    <span class="text-sm text-gray-500">60%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: 60%"></div>
                </div>
            </div>
            
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Notification System</span>
                    <span class="text-sm text-gray-500">45%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-yellow-600 h-2 rounded-full" style="width: 45%"></div>
                </div>
            </div>
            
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Reporting & Analytics</span>
                    <span class="text-sm text-gray-500">30%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-orange-600 h-2 rounded-full" style="width: 30%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Feature Preview Section -->
<div class="w-full mt-8">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Feature Preview</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                <div class="text-center mb-3">
                    <i class="fas fa-book-open text-3xl text-blue-600 mb-2"></i>
                    <h4 class="font-semibold text-blue-800">Syllabus Tracking</h4>
                </div>
                <p class="text-blue-700 text-sm text-center">Monitor syllabus updates, track changes, and ensure curriculum compliance across all courses.</p>
            </div>
            
            <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                <div class="text-center mb-3">
                    <i class="fas fa-tasks text-3xl text-green-600 mb-2"></i>
                    <h4 class="font-semibold text-green-800">Assignment Monitoring</h4>
                </div>
                <p class="text-green-700 text-sm text-center">Track quiz assignments, homework submissions, and student engagement in real-time.</p>
            </div>
            
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                <div class="text-center mb-3">
                    <i class="fas fa-chart-bar text-3xl text-purple-600 mb-2"></i>
                    <h4 class="font-semibold text-purple-800">Analytics Dashboard</h4>
                </div>
                <p class="text-purple-700 text-sm text-center">Comprehensive reports on teacher activity, student performance, and course effectiveness.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Add some interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to the feature list items
    const featureItems = document.querySelectorAll('.bg-blue-50 li');
    featureItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Add progress bar animation
    const progressBars = document.querySelectorAll('.bg-blue-600, .bg-green-600, .bg-yellow-600, .bg-orange-600');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = width;
        }, 500);
    });
    
    // Add hover effects to feature preview cards
    const featureCards = document.querySelectorAll('.bg-gradient-to-br');
    featureCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
            this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
});
</script>
