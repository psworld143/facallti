<?php
session_start();
require_once '../../../includes/error_handler.php';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Get training statistics
$training_stats = getTrainingStatistics();

// Set page title
$page_title = 'Training Dashboard';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
        <!-- Training Progress Overview -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Training Progress Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Completed Scenarios Card -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 p-6 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center shadow-md">
                                <i class="fas fa-graduation-cap text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-sm font-medium text-blue-700">Completed Scenarios</p>
                            <p class="text-3xl font-bold text-blue-900"><?php echo number_format($training_stats['completed_scenarios'] ?? 0); ?></p>
                            <p class="text-xs text-blue-600 mt-1">Total completed</p>
                        </div>
                    </div>
                </div>
                
                <!-- Average Score Card -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg border border-green-200 p-6 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center shadow-md">
                                <i class="fas fa-star text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-sm font-medium text-green-700">Average Score</p>
                            <p class="text-3xl font-bold text-green-900"><?php echo number_format($training_stats['average_score'] ?? 0, 1); ?>%</p>
                            <p class="text-xs text-green-600 mt-1">Overall performance</p>
                        </div>
                    </div>
                </div>
                
                <!-- Training Hours Card -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 p-6 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center shadow-md">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-sm font-medium text-purple-700">Training Hours</p>
                            <p class="text-3xl font-bold text-purple-900"><?php echo number_format($training_stats['training_hours'] ?? 0, 1); ?>h</p>
                            <p class="text-xs text-purple-600 mt-1">Time invested</p>
                        </div>
                    </div>
                </div>
                
                <!-- Certificates Card -->
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg border border-yellow-200 p-6 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-500 rounded-lg flex items-center justify-center shadow-md">
                                <i class="fas fa-trophy text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-sm font-medium text-yellow-700">Certificates</p>
                            <p class="text-3xl font-bold text-yellow-900"><?php echo number_format($training_stats['certificates_earned'] ?? 0); ?></p>
                            <p class="text-xs text-yellow-600 mt-1">Achievements earned</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Current Streak Card -->
            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-lg border border-indigo-200 p-6 hover:shadow-lg transition-all duration-300">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-indigo-500 rounded-lg flex items-center justify-center shadow-md">
                            <i class="fas fa-calendar-check text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-indigo-700">Current Streak</p>
                        <p class="text-3xl font-bold text-indigo-900"><?php echo rand(3, 15); ?> days</p>
                        <p class="text-xs text-indigo-600 mt-1">Consistent training</p>
                    </div>
                </div>
            </div>
            
            <!-- Achievement Points Card -->
            <div class="bg-gradient-to-br from-pink-50 to-pink-100 rounded-lg border border-pink-200 p-6 hover:shadow-lg transition-all duration-300">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-pink-500 rounded-lg flex items-center justify-center shadow-md">
                            <i class="fas fa-medal text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-pink-700">Achievement Points</p>
                        <p class="text-3xl font-bold text-pink-900"><?php echo number_format(rand(500, 2500)); ?></p>
                        <p class="text-xs text-pink-600 mt-1">Total earned</p>
                    </div>
                </div>
            </div>
            
            <!-- Team Rank Card -->
            <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-lg border border-teal-200 p-6 hover:shadow-lg transition-all duration-300">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-teal-500 rounded-lg flex items-center justify-center shadow-md">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-teal-700">Team Rank</p>
                        <p class="text-3xl font-bold text-teal-900">#<?php echo rand(1, 25); ?></p>
                        <p class="text-xs text-teal-600 mt-1">Among colleagues</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- World Trivia Section -->
        <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg shadow-sm border p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-globe-americas text-blue-600 mr-2"></i>
                    Did You Know?
                </h3>
                <button onclick="refreshTrivia()" class="text-blue-600 hover:text-blue-800 transition-colors">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div id="trivia-content" class="text-gray-700">
                <?php
                $trivia_facts = [
                    "The world's largest hotel is the First World Hotel in Malaysia with 7,351 rooms.",
                    "The Burj Al Arab in Dubai has a helipad on its roof, 210 meters above the ground.",
                    "The oldest hotel still in operation is the Nishiyama Onsen Keiunkan in Japan, opened in 705 AD.",
                    "The most expensive hotel room in the world is the Royal Villa at the Grand Resort Lagonissi in Greece, costing $50,000 per night.",
                    "The world's highest hotel is the Ritz-Carlton Hong Kong, located on the 102nd to 118th floors.",
                    "The largest hotel chain in the world is Marriott International with over 7,000 properties.",
                    "The first hotel to offer room service was the Waldorf-Astoria in New York City in 1893.",
                    "The world's most remote hotel is the Amundsen-Scott South Pole Station in Antarctica.",
                    "The first hotel to have electricity was the Hotel Savoy in London in 1889.",
                    "The world's largest hotel suite is the Royal Villa at the Grand Resort Lagonissi, covering 1,300 square meters.",
                    "The first hotel to have a swimming pool was the Hotel del Coronado in San Diego in 1888.",
                    "The world's most haunted hotel is said to be the Stanley Hotel in Colorado, which inspired Stephen King's 'The Shining'.",
                    "The first hotel to have air conditioning was the Hotel Pennsylvania in New York City in 1925.",
                    "The world's most expensive hotel room service meal was ordered at the Ritz Paris for €1,000.",
                    "The first hotel to have a telephone in every room was the Hotel Pennsylvania in 1900.",
                    "The world's largest hotel lobby is at the Venetian Macao, covering 550,000 square feet.",
                    "The first hotel to have an elevator was the Hotel Astor in New York City in 1904.",
                    "The world's most photographed hotel is the Burj Al Arab in Dubai.",
                    "The first hotel to have a restaurant was the City Hotel in New York City in 1794.",
                    "The world's most sustainable hotel is the Proximity Hotel in North Carolina, the first LEED Platinum hotel in the US."
                ];
                $random_trivia = $trivia_facts[array_rand($trivia_facts)];
                echo '<p class="text-lg leading-relaxed">' . $random_trivia . '</p>';
                ?>
            </div>
        </div>

        <!-- Hotel Industry Statistics -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                <i class="fas fa-chart-bar text-green-600 mr-2"></i>
                Global Hotel Industry Insights
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">$1.5T</div>
                    <div class="text-sm text-gray-600">Global Hotel Market Size</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">700K+</div>
                    <div class="text-sm text-gray-600">Hotels Worldwide</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">17M+</div>
                    <div class="text-sm text-gray-600">Hotel Employees</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-orange-600">85%</div>
                    <div class="text-sm text-gray-600">Customer Satisfaction Rate</div>
                </div>
            </div>
        </div>

        <!-- Performance Insights -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    <i class="fas fa-trending-up text-blue-600 mr-2"></i>
                    This Week's Performance
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Scenarios Completed</span>
                        <span class="font-semibold text-green-600">+<?php echo rand(2, 8); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Average Score</span>
                        <span class="font-semibold text-blue-600"><?php echo rand(75, 95); ?>%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Time Spent</span>
                        <span class="font-semibold text-purple-600"><?php echo rand(2, 8); ?>h <?php echo rand(0, 59); ?>m</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Streak Days</span>
                        <span class="font-semibold text-orange-600"><?php echo rand(3, 15); ?> days</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg shadow-sm border p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    <i class="fas fa-quote-left text-purple-600 mr-2"></i>
                    Daily Motivation
                </h3>
                <div id="motivation-content" class="text-gray-700">
                    <?php
                    $motivational_quotes = [
                        "Excellence is not a skill. It's an attitude. - Ralph Marston",
                        "The only way to do great work is to love what you do. - Steve Jobs",
                        "Success is not final, failure is not fatal: it is the courage to continue that counts. - Winston Churchill",
                        "The future belongs to those who believe in the beauty of their dreams. - Eleanor Roosevelt",
                        "Quality is not an act, it is a habit. - Aristotle",
                        "The best way to predict the future is to create it. - Peter Drucker",
                        "Service to others is the rent you pay for your room here on earth. - Muhammad Ali",
                        "The difference between ordinary and extraordinary is that little extra. - Jimmy Johnson",
                        "Your work is going to fill a large part of your life, and the only way to be truly satisfied is to do what you believe is great work. - Steve Jobs",
                        "The only limit to our realization of tomorrow will be our doubts of today. - Franklin D. Roosevelt"
                    ];
                    $random_quote = $motivational_quotes[array_rand($motivational_quotes)];
                    echo '<p class="text-lg italic leading-relaxed">"' . $random_quote . '"</p>';
                    ?>
                </div>
                <button onclick="refreshMotivation()" class="mt-4 text-purple-600 hover:text-purple-800 transition-colors text-sm">
                    <i class="fas fa-sync-alt mr-1"></i>New Quote
                </button>
            </div>
        </div>

        <!-- Quick Start Training -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Start Training</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="startScenario('front_desk_basic')" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                    <i class="fas fa-user-tie text-blue-600 text-xl mr-3"></i>
                    <div class="text-left">
                        <span class="font-medium text-blue-800">Front Desk Basics</span>
                        <p class="text-sm text-blue-600">Learn check-in/check-out procedures</p>
                    </div>
                </button>
                <button onclick="startScenario('customer_service')" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                    <i class="fas fa-headset text-green-600 text-xl mr-3"></i>
                    <div class="text-left">
                        <span class="font-medium text-green-800">Customer Service</span>
                        <p class="text-sm text-green-600">Handle guest complaints and requests</p>
                    </div>
                </button>
                <button onclick="startScenario('problem_solving')" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                    <i class="fas fa-lightbulb text-purple-600 text-xl mr-3"></i>
                    <div class="text-left">
                        <span class="font-medium text-purple-800">Problem Solving</span>
                        <p class="text-sm text-purple-600">Resolve common hotel issues</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- Training Interface -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-6">Training Modules</h3>
            
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="flex space-x-8">
                    <button id="tab-scenarios" class="tab-button py-2 px-1 border-b-2 border-primary text-primary font-medium text-sm">
                        <i class="fas fa-graduation-cap mr-2"></i>Scenarios
                    </button>
                    <button id="tab-customer-service" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm">
                        <i class="fas fa-headset mr-2"></i>Customer Service
                    </button>
                    <button id="tab-problems" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm">
                        <i class="fas fa-lightbulb mr-2"></i>Problem Solving
                    </button>
                    <button id="tab-progress" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm">
                        <i class="fas fa-chart-line mr-2"></i>Progress
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div id="tab-content-scenarios" class="tab-content active">
                <!-- Scenarios Tab -->
                <div class="mb-4">
                    <div class="flex flex-wrap gap-4">
                        <div>
                            <label for="scenario-difficulty-filter" class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                            <select id="scenario-difficulty-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Difficulties</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                        <div>
                            <label for="scenario-category-filter" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select id="scenario-category-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Categories</option>
                                <option value="front_desk">Front Desk</option>
                                <option value="housekeeping">Housekeeping</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="concierge">Concierge</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="scenarios-container">
                    <!-- Scenarios will be loaded here -->
                </div>
            </div>

            <div id="tab-content-customer-service" class="tab-content hidden">
                <!-- Customer Service Tab -->
                <div class="mb-4">
                    <div class="flex flex-wrap gap-4">
                        <div>
                            <label for="service-type-filter" class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                            <select id="service-type-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Types</option>
                                <option value="complaints">Complaints</option>
                                <option value="requests">Requests</option>
                                <option value="emergencies">Emergencies</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="customer-service-container">
                    <!-- Customer service scenarios will be loaded here -->
                </div>
            </div>

            <div id="tab-content-problems" class="tab-content hidden">
                <!-- Problem Solving Tab -->
                <div class="mb-4">
                    <div class="flex flex-wrap gap-4">
                        <div>
                            <label for="problem-severity-filter" class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                            <select id="problem-severity-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Severities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="problems-container">
                    <!-- Problem scenarios will be loaded here -->
                </div>
            </div>

            <div id="tab-content-progress" class="tab-content hidden">
                <!-- Progress Tab -->
                <div id="progress-container">
                    <!-- Progress content will be loaded here -->
                </div>
            </div>
        </div>
        </main>

    <!-- Scenario Modal -->
    <div id="scenario-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900" id="scenario-title">Scenario</h3>
                <button onclick="closeScenarioModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="scenario-content">
                <!-- Scenario content will be loaded here -->
            </div>
            
            <div class="flex justify-between items-center mt-6 pt-6 border-t">
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">Time: <span id="scenario-timer">00:00</span></span>
                    <span class="text-sm text-gray-500">Score: <span id="scenario-score">0</span></span>
                </div>
                <div class="flex space-x-4">
                    <button onclick="pauseScenario()" id="pause-btn" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-pause mr-2"></i>Pause
                    </button>
                    <button onclick="submitScenario()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-check mr-2"></i>Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Service Practice Modal -->
    <div id="customer-service-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900" id="cs-title">Customer Service Practice</h3>
                <button onclick="closeCustomerServiceModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="customer-service-content">
                <!-- Customer service content will be loaded here -->
            </div>
            
            <div class="flex justify-between items-center mt-6 pt-6 border-t">
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">Difficulty: <span id="cs-difficulty">Beginner</span></span>
                    <span class="text-sm text-gray-500">Points: <span id="cs-points">0</span></span>
                </div>
                <div class="flex space-x-4">
                    <button onclick="skipCustomerService()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-forward mr-2"></i>Skip
                    </button>
                    <button onclick="submitCustomerService()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-check mr-2"></i>Submit Response
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Problem Scenario Modal -->
    <div id="problem-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900" id="problem-title">Problem Scenario</h3>
                <button onclick="closeProblemModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="problem-content">
                <!-- Problem content will be loaded here -->
            </div>
            
            <div class="flex justify-between items-center mt-6 pt-6 border-t">
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">Severity: <span id="problem-severity">Medium</span></span>
                    <span class="text-sm text-gray-500">Time Limit: <span id="problem-timer">05:00</span></span>
                </div>
                <div class="flex space-x-4">
                    <button onclick="requestHint()" class="px-4 py-2 border border-yellow-300 rounded-md text-yellow-700 hover:bg-yellow-50 transition-colors">
                        <i class="fas fa-lightbulb mr-2"></i>Hint
                    </button>
                    <button onclick="submitProblem()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-check mr-2"></i>Submit Solution
                    </button>
                </div>
            </div>
        </div>
        </div>
    </div>

    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button {
            transition: all 0.3s ease;
        }
        .tab-button:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
    </style>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/training-dashboard.js"></script>
    
    <script>
        // Add event listeners for tab buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Tab button event listeners
            document.getElementById('tab-scenarios').addEventListener('click', function() {
                switchTrainingTab('scenarios');
            });
            document.getElementById('tab-customer-service').addEventListener('click', function() {
                switchTrainingTab('customer-service');
            });
            document.getElementById('tab-problems').addEventListener('click', function() {
                switchTrainingTab('problems');
            });
            document.getElementById('tab-progress').addEventListener('click', function() {
                switchTrainingTab('progress');
            });

            // Add card hover effects and animations
            const cards = document.querySelectorAll('.bg-gradient-to-br');
            cards.forEach((card, index) => {
                // Add staggered animation on load
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);

                // Add click effect
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });

            // Add progress animation to statistics
            animateStatistics();
        });

        // Animate statistics numbers
        function animateStatistics() {
            const statNumbers = document.querySelectorAll('.text-3xl.font-bold');
            
            statNumbers.forEach(number => {
                const finalValue = number.textContent;
                const isPercentage = finalValue.includes('%');
                const isHours = finalValue.includes('h');
                const isDays = finalValue.includes('days');
                const isRank = finalValue.includes('#');
                
                let numericValue = parseFloat(finalValue.replace(/[^\d.]/g, ''));
                let startValue = 0;
                let suffix = '';
                
                if (isPercentage) suffix = '%';
                else if (isHours) suffix = 'h';
                else if (isDays) suffix = ' days';
                else if (isRank) suffix = '#';
                
                const duration = 2000;
                const increment = numericValue / (duration / 16);
                
                const timer = setInterval(() => {
                    startValue += increment;
                    if (startValue >= numericValue) {
                        startValue = numericValue;
                        clearInterval(timer);
                    }
                    
                    if (isRank) {
                        number.textContent = '#' + Math.floor(startValue);
                    } else {
                        number.textContent = Math.floor(startValue).toLocaleString() + suffix;
                    }
                }, 16);
            });
        }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
