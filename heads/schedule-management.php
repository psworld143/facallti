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
$page_title = 'Schedule Management';

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Schedule Management</h1>
            <p class="text-gray-600">Manage class schedules and timetables</p>
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
            <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-calendar-alt text-4xl text-yellow-600"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Coming Soon!</h2>
            <p class="text-lg text-gray-600">Schedule Management feature is under development</p>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-yellow-600 mt-1 mr-3 text-lg"></i>
                <div class="text-left">
                    <h3 class="font-semibold text-yellow-800 mb-2">What's Coming:</h3>
                    <ul class="text-yellow-700 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-yellow-600"></i>
                            Assign teaching schedules to department teachers
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-yellow-600"></i>
                            Set up semester timetables and class periods
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-yellow-600"></i>
                            Manage teacher workload and time distribution
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-yellow-600"></i>
                            Resolve schedule conflicts between teachers
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle mr-2 text-yellow-600"></i>
                            Generate teaching schedule reports
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-start">
                <i class="fas fa-clock text-blue-600 mt-1 mr-3 text-lg"></i>
                <div class="text-left">
                    <h3 class="font-semibold text-blue-800 mb-2">Expected Features:</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-blue-700">
                        <div>
                            <h4 class="font-medium mb-2">Schedule Assignment</h4>
                            <ul class="text-sm space-y-1">
                                <li>• Weekly teaching schedule creation</li>
                                <li>• Semester timetable planning</li>
                                <li>• Teacher workload management</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-medium mb-2">Conflict Management</h4>
                            <ul class="text-sm space-y-1">
                                <li>• Teacher schedule conflict detection</li>
                                <li>• Teaching hour validation</li>
                                <li>• Workload balance checking</li>
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
                    <span class="text-sm font-medium text-gray-700">Database Design</span>
                    <span class="text-sm text-gray-500">100%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: 100%"></div>
                </div>
            </div>
            
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Backend API</span>
                    <span class="text-sm text-gray-500">75%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: 75%"></div>
                </div>
            </div>
            
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Frontend Interface</span>
                    <span class="text-sm text-gray-500">40%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-yellow-600 h-2 rounded-full" style="width: 40%"></div>
                </div>
            </div>
            
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Testing & Integration</span>
                    <span class="text-sm text-gray-500">20%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-orange-600 h-2 rounded-full" style="width: 20%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add some interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to the feature list items
    const featureItems = document.querySelectorAll('.bg-yellow-50 li');
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
    const progressBars = document.querySelectorAll('.bg-green-600, .bg-blue-600, .bg-yellow-600, .bg-orange-600');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = width;
        }, 500);
    });
});
</script>
