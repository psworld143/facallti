<?php
session_start();
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

    <div class="main-content">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Training Progress Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Completed Scenarios</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($training_stats['completed_scenarios']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-star text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Average Score</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($training_stats['average_score'], 1); ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Training Hours</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($training_stats['training_hours'], 1); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-trophy text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Certificates</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($training_stats['certificates_earned']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-indigo-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Current Streak</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo rand(3, 15); ?> days</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-pink-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-medal text-pink-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Achievement Points</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format(rand(500, 2500)); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-teal-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-teal-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Team Rank</p>
                        <p class="text-2xl font-semibold text-gray-900">#<?php echo rand(1, 25); ?></p>
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


    </div>

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

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/training-dashboard.js"></script>
    
    <?php include '../../includes/footer.php'; ?>
