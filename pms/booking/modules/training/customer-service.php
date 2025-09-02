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

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get filter parameters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

// Fetch customer service scenarios from database
try {
    // Get customer service scenarios with filters
    $where_conditions = ["1=1"];
    $params = [];
    
    if (!empty($type_filter)) {
        $where_conditions[] = "type = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($difficulty_filter)) {
        $where_conditions[] = "difficulty = ?";
        $params[] = $difficulty_filter;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            css.*,
            COALESCE(AVG(ta.score), 0) as avg_score,
            COUNT(ta.id) as attempt_count
        FROM customer_service_scenarios css
        LEFT JOIN training_attempts ta ON css.id = ta.scenario_id AND ta.scenario_type = 'customer_service'
        WHERE {$where_clause}
        GROUP BY css.id
        ORDER BY css.difficulty, css.title
    ");
    $stmt->execute($params);
    $scenarios = $stmt->fetchAll();

    // Get user's completed customer service scenarios
    $stmt = $pdo->prepare("
        SELECT scenario_id, score, status, created_at
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'customer_service'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $user_attempts = $stmt->fetchAll();
    
    // Create a map of user attempts for quick lookup
    $user_attempts_map = [];
    foreach ($user_attempts as $attempt) {
        $user_attempts_map[$attempt['scenario_id']] = $attempt;
    }

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_scenarios,
            COUNT(CASE WHEN type = 'complaints' THEN 1 END) as complaints_count,
            COUNT(CASE WHEN type = 'requests' THEN 1 END) as requests_count,
            COUNT(CASE WHEN type = 'emergencies' THEN 1 END) as emergencies_count,
            COUNT(CASE WHEN difficulty = 'beginner' THEN 1 END) as beginner_count,
            COUNT(CASE WHEN difficulty = 'intermediate' THEN 1 END) as intermediate_count,
            COUNT(CASE WHEN difficulty = 'advanced' THEN 1 END) as advanced_count
        FROM customer_service_scenarios
    ");
    $stmt->execute();
    $stats = $stmt->fetch();

    // Get user's customer service statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as completed_scenarios,
            AVG(score) as avg_score,
            SUM(duration_minutes) as total_time,
            COUNT(CASE WHEN score >= 80 THEN 1 END) as high_performance_count
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'customer_service' AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $user_stats = $stmt->fetch();

    // Get performance by scenario type
    $stmt = $pdo->prepare("
        SELECT 
            css.type,
            COUNT(ta.id) as attempts,
            AVG(ta.score) as avg_score
        FROM customer_service_scenarios css
        LEFT JOIN training_attempts ta ON css.id = ta.scenario_id AND ta.scenario_type = 'customer_service' AND ta.user_id = ?
        GROUP BY css.type
    ");
    $stmt->execute([$user_id]);
    $performance_by_type = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching customer service scenarios: " . $e->getMessage());
    $scenarios = [];
    $user_attempts = [];
    $user_attempts_map = [];
    $stats = [
        'total_scenarios' => 0,
        'complaints_count' => 0,
        'requests_count' => 0,
        'emergencies_count' => 0,
        'beginner_count' => 0,
        'intermediate_count' => 0,
        'advanced_count' => 0
    ];
    $user_stats = [
        'completed_scenarios' => 0,
        'avg_score' => 0,
        'total_time' => 0,
        'high_performance_count' => 0
    ];
    $performance_by_type = [];
}

// Set page title for unified header
$page_title = 'Customer Service Training';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            

            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Customer Service Scenarios</h2>
                        <p class="text-gray-600 mt-1">Master the art of handling guest complaints, requests, and emergencies</p>
                    </div>
                    <div class="flex space-x-3">
                        <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-headset mr-2"></i>Practice Mode
                        </button>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-certificate mr-2"></i>Get Certified
                        </button>
                    </div>
                </div>
            </div>

            <!-- User Performance Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Completed</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $user_stats['completed_scenarios']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-star text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Average Score</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($user_stats['avg_score'] ?? 0, 1); ?>%</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-trophy text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">High Performance</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $user_stats['high_performance_count']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Training Time</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo round($user_stats['total_time'] / 60, 1); ?>h</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Performance by Type -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Performance by Type</h3>
                    <div class="space-y-4">
                        <?php foreach ($performance_by_type as $performance): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-3 
                                        <?php 
                                        switch($performance['type']) {
                                            case 'complaints': echo 'bg-red-500'; break;
                                            case 'requests': echo 'bg-blue-500'; break;
                                            case 'emergencies': echo 'bg-yellow-500'; break;
                                            default: echo 'bg-gray-500';
                                        }
                                        ?>"></div>
                                    <span class="text-sm font-medium text-gray-600"><?php echo ucfirst($performance['type']); ?></span>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo number_format($performance['avg_score'] ?? 0, 1); ?>%</p>
                                    <p class="text-xs text-gray-500"><?php echo $performance['attempts']; ?> attempts</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Tips -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Customer Service Tips</h3>
                    <div class="space-y-3">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-lightbulb text-yellow-500 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Listen Actively</p>
                                <p class="text-xs text-gray-600">Pay full attention to guest concerns</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-heart text-red-500 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Show Empathy</p>
                                <p class="text-xs text-gray-600">Acknowledge their feelings</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-bolt text-blue-500 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Act Quickly</p>
                                <p class="text-xs text-gray-600">Respond promptly to urgent issues</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check text-green-500 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Follow Up</p>
                                <p class="text-xs text-gray-600">Ensure resolution is satisfactory</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        <?php 
                        $recent_attempts = array_slice($user_attempts, 0, 3);
                        if (empty($recent_attempts)): ?>
                            <p class="text-sm text-gray-500">No recent activity</p>
                        <?php else: ?>
                            <?php foreach ($recent_attempts as $attempt): ?>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">Scenario completed</p>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($attempt['created_at'])); ?></p>
                                    </div>
                                    <span class="text-sm font-semibold text-green-600"><?php echo number_format($attempt['score'] ?? 0, 1); ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" id="search-scenarios" placeholder="Search customer service scenarios..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <select id="type-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Types</option>
                        <option value="complaints" <?php echo $type_filter === 'complaints' ? 'selected' : ''; ?>>Complaints</option>
                        <option value="requests" <?php echo $type_filter === 'requests' ? 'selected' : ''; ?>>Requests</option>
                        <option value="emergencies" <?php echo $type_filter === 'emergencies' ? 'selected' : ''; ?>>Emergencies</option>
                    </select>
                    <select id="difficulty-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Difficulties</option>
                        <option value="beginner" <?php echo $difficulty_filter === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo $difficulty_filter === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo $difficulty_filter === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                    <button id="clear-filters" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </button>
                </div>
            </div>

            <!-- Scenarios Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (empty($scenarios)): ?>
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-headset text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No scenarios found</h3>
                        <p class="text-gray-500">Try adjusting your filters or check back later for new scenarios.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($scenarios as $scenario): ?>
                        <?php 
                        $user_attempt = isset($user_attempts_map[$scenario['id']]) ? $user_attempts_map[$scenario['id']] : null;
                        $is_completed = $user_attempt && $user_attempt['status'] === 'completed';
                        $best_score = $user_attempt ? $user_attempt['score'] : 0;
                        
                        // Difficulty colors
                        $difficulty_colors = [
                            'beginner' => 'bg-green-100 text-green-800',
                            'intermediate' => 'bg-yellow-100 text-yellow-800',
                            'advanced' => 'bg-red-100 text-red-800'
                        ];
                        
                        // Type colors and icons
                        $type_colors = [
                            'complaints' => 'bg-red-100 text-red-800',
                            'requests' => 'bg-blue-100 text-blue-800',
                            'emergencies' => 'bg-yellow-100 text-yellow-800'
                        ];
                        
                        $type_icons = [
                            'complaints' => 'fas fa-exclamation-triangle',
                            'requests' => 'fas fa-hand-paper',
                            'emergencies' => 'fas fa-exclamation-circle'
                        ];
                        ?>
                        
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-300 scenario-card flex flex-col" 
                             data-type="<?php echo $scenario['type']; ?>" 
                             data-difficulty="<?php echo $scenario['difficulty']; ?>"
                             data-title="<?php echo strtolower($scenario['title']); ?>">
                            
                            <!-- Scenario Header -->
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center">
                                        <i class="<?php echo $type_icons[$scenario['type']]; ?> text-blue-600 text-xl mr-3"></i>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($scenario['title']); ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo ucfirst($scenario['type']); ?></p>
                                        </div>
                                    </div>
                                    <?php if ($is_completed): ?>
                                        <div class="flex items-center">
                                            <i class="fas fa-check-circle text-green-500 text-xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars(substr($scenario['description'], 0, 100) . (strlen($scenario['description']) > 100 ? '...' : '')); ?></p>
                                
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $difficulty_colors[$scenario['difficulty']]; ?>">
                                        <?php echo ucfirst($scenario['difficulty']); ?>
                                    </span>
                                    <div class="flex items-center text-sm text-gray-500">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo $scenario['estimated_time']; ?> min
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Scenario Content Preview -->
                            <div class="p-6 flex-grow">
                                <div class="mb-4">
                                    <h4 class="text-sm font-medium text-gray-800 mb-2">Situation:</h4>
                                    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($scenario['situation'], 0, 80) . (strlen($scenario['situation']) > 80 ? '...' : '')); ?></p>
                                    
                                    <h4 class="text-sm font-medium text-gray-800 mb-2">Guest Request:</h4>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars(substr($scenario['guest_request'], 0, 80) . (strlen($scenario['guest_request']) > 80 ? '...' : '')); ?></p>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Points</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo $scenario['points']; ?></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Avg Score</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo number_format($scenario['avg_score'] ?? 0, 1); ?>%</p>
                                    </div>
                                </div>
                                
                                <?php if ($is_completed): ?>
                                    <div class="mb-4 p-3 bg-green-50 rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-green-800">Your Score</span>
                                            <span class="text-lg font-bold text-green-600"><?php echo number_format($best_score, 1); ?>%</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Empty space to maintain consistent height -->
                                    <div class="mb-4 p-3 bg-transparent rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-transparent">Your Score</span>
                                            <span class="text-lg font-bold text-transparent">0%</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Action Buttons - Always at bottom -->
                            <div class="p-6 pt-0">
                                <div class="flex space-x-2">
                                    <?php if ($is_completed): ?>
                                        <button onclick="retakeScenario(<?php echo $scenario['id']; ?>)" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                            <i class="fas fa-redo mr-2"></i>Retake
                                        </button>
                                        <button onclick="viewResults(<?php echo $scenario['id']; ?>)" class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors text-sm">
                                            <i class="fas fa-chart-bar mr-2"></i>Results
                                        </button>
                                    <?php else: ?>
                                        <button onclick="startScenario(<?php echo $scenario['id']; ?>)" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                            <i class="fas fa-play mr-2"></i>Start
                                        </button>
                                        <button onclick="previewScenario(<?php echo $scenario['id']; ?>)" class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors text-sm">
                                            <i class="fas fa-eye mr-2"></i>Preview
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // Wait for document to be ready
        $(document).ready(function() {
            console.log('Document ready, initializing customer service filters...');
            
            // Search functionality
            $('#search-scenarios').on('input', function() {
                console.log('Search input detected');
                const searchTerm = $(this).val().toLowerCase();
                $('.scenario-card').each(function() {
                    const title = $(this).data('title');
                    const description = $(this).find('p').text().toLowerCase();
                    
                    if (title.includes(searchTerm) || description.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Type filter
            $('#type-filter').on('change', function() {
                console.log('Type filter changed:', $(this).val());
                const selectedType = $(this).val();
                $('.scenario-card').each(function() {
                    const type = $(this).data('type');
                    
                    if (selectedType === '' || type === selectedType) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Difficulty filter
            $('#difficulty-filter').on('change', function() {
                console.log('Difficulty filter changed:', $(this).val());
                const selectedDifficulty = $(this).val();
                $('.scenario-card').each(function() {
                    const difficulty = $(this).data('difficulty');
                    
                    if (selectedDifficulty === '' || difficulty === selectedDifficulty) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Clear filters
            $('#clear-filters').on('click', function() {
                console.log('Clear filters clicked');
                $('#search-scenarios').val('');
                $('#type-filter').val('');
                $('#difficulty-filter').val('');
                $('.scenario-card').show();
            });
            
            console.log('Customer service filters initialized successfully');
        });

        // Scenario action functions
        function startScenario(scenarioId) {
            // Redirect to scenario start page
            window.location.href = `customer-service-start.php?id=${scenarioId}`;
        }

        function retakeScenario(scenarioId) {
            if (confirm('Are you sure you want to retake this scenario? Your previous score will be saved.')) {
                window.location.href = `customer-service-start.php?id=${scenarioId}&retake=1`;
            }
        }

        function previewScenario(scenarioId) {
            // Open preview modal or redirect to preview page
            window.location.href = `customer-service-preview.php?id=${scenarioId}`;
        }

        function viewResults(scenarioId) {
            // Redirect to results page
            window.location.href = `customer-service-results.php?id=${scenarioId}`;
        }

        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            
            $('#current-date').text(now.toLocaleDateString('en-US', dateOptions));
            $('#current-time').text(now.toLocaleTimeString('en-US', timeOptions));
        }

        // Update time every second
        setInterval(updateDateTime, 1000);
        updateDateTime();
    </script>
</body>
</html>
