<?php
session_start();
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
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

// Fetch problem-solving scenarios from database
try {
    // Get problem-solving scenarios with filters
    $where_conditions = ["1=1"];
    $params = [];
    
    if (!empty($severity_filter)) {
        $where_conditions[] = "severity = ?";
        $params[] = $severity_filter;
    }
    
    if (!empty($difficulty_filter)) {
        $where_conditions[] = "difficulty = ?";
        $params[] = $difficulty_filter;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            ps.*,
            COALESCE(AVG(ta.score), 0) as avg_score,
            COUNT(ta.id) as attempt_count
        FROM problem_scenarios ps
        LEFT JOIN training_attempts ta ON ps.id = ta.scenario_id AND ta.scenario_type = 'problem_solving'
        WHERE {$where_clause}
        GROUP BY ps.id
        ORDER BY ps.severity, ps.difficulty, ps.title
    ");
    $stmt->execute($params);
    $scenarios = $stmt->fetchAll();

    // Get user's completed problem-solving scenarios
    $stmt = $pdo->prepare("
        SELECT scenario_id, score, status, created_at
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'problem_solving'
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
            COUNT(CASE WHEN severity = 'low' THEN 1 END) as low_count,
            COUNT(CASE WHEN severity = 'medium' THEN 1 END) as medium_count,
            COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_count,
            COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_count,
            COUNT(CASE WHEN difficulty = 'beginner' THEN 1 END) as beginner_count,
            COUNT(CASE WHEN difficulty = 'intermediate' THEN 1 END) as intermediate_count,
            COUNT(CASE WHEN difficulty = 'advanced' THEN 1 END) as advanced_count
        FROM problem_scenarios
    ");
    $stmt->execute();
    $stats = $stmt->fetch();

    // Get user's problem-solving statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as completed_scenarios,
            AVG(score) as avg_score,
            SUM(duration_minutes) as total_time,
            COUNT(CASE WHEN score >= 85 THEN 1 END) as excellent_performance_count,
            COUNT(CASE WHEN score >= 70 AND score < 85 THEN 1 END) as good_performance_count
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'problem_solving' AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $user_stats = $stmt->fetch();

    // Get performance by severity level
    $stmt = $pdo->prepare("
        SELECT 
            ps.severity,
            COUNT(ta.id) as attempts,
            AVG(ta.score) as avg_score
        FROM problem_scenarios ps
        LEFT JOIN training_attempts ta ON ps.id = ta.scenario_id AND ta.scenario_type = 'problem_solving' AND ta.user_id = ?
        GROUP BY ps.severity
        ORDER BY 
            CASE ps.severity
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END
    ");
    $stmt->execute([$user_id]);
    $performance_by_severity = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching problem-solving scenarios: " . $e->getMessage());
    $scenarios = [];
    $user_attempts = [];
    $user_attempts_map = [];
    $stats = [
        'total_scenarios' => 0,
        'low_count' => 0,
        'medium_count' => 0,
        'high_count' => 0,
        'critical_count' => 0,
        'beginner_count' => 0,
        'intermediate_count' => 0,
        'advanced_count' => 0
    ];
    $user_stats = [
        'completed_scenarios' => 0,
        'avg_score' => 0,
        'total_time' => 0,
        'excellent_performance_count' => 0,
        'good_performance_count' => 0
    ];
    $performance_by_severity = [];
}

// Set page title for unified header
$page_title = 'Problem Solving Training';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Problem Solving Training</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Problem Solving Scenarios</h2>
                        <p class="text-gray-600 mt-1">Develop critical thinking skills to handle complex hotel operational challenges</p>
                    </div>
                    <div class="flex space-x-3">
                        <button class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors">
                            <i class="fas fa-puzzle-piece mr-2"></i>Challenge Mode
                        </button>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-trophy mr-2"></i>Leaderboard
                        </button>
                    </div>
                </div>
            </div>

            <!-- User Performance Stats -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
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
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($user_stats['avg_score'], 1); ?>%</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-crown text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Excellent</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $user_stats['excellent_performance_count']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <i class="fas fa-medal text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Good</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $user_stats['good_performance_count']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-red-100 rounded-lg">
                            <i class="fas fa-clock text-red-600 text-xl"></i>
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
                <!-- Performance by Severity -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Performance by Severity</h3>
                    <div class="space-y-4">
                        <?php foreach ($performance_by_severity as $performance): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-3 
                                        <?php 
                                        switch($performance['severity']) {
                                            case 'critical': echo 'bg-red-500'; break;
                                            case 'high': echo 'bg-orange-500'; break;
                                            case 'medium': echo 'bg-yellow-500'; break;
                                            case 'low': echo 'bg-green-500'; break;
                                            default: echo 'bg-gray-500';
                                        }
                                        ?>"></div>
                                    <span class="text-sm font-medium text-gray-600"><?php echo ucfirst($performance['severity']); ?></span>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo number_format($performance['avg_score'], 1); ?>%</p>
                                    <p class="text-xs text-gray-500"><?php echo $performance['attempts']; ?> attempts</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Problem Solving Tips -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Problem Solving Tips</h3>
                    <div class="space-y-3">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-search text-blue-500 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Analyze the Problem</p>
                                <p class="text-xs text-gray-600">Identify root causes and key factors</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-lightbulb text-yellow-500 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Generate Solutions</p>
                                <p class="text-xs text-gray-600">Consider multiple approaches</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-balance-scale text-green-500 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Evaluate Options</p>
                                <p class="text-xs text-gray-600">Weigh pros and cons carefully</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-rocket text-purple-500 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Implement & Monitor</p>
                                <p class="text-xs text-gray-600">Execute and track results</p>
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
                                        <p class="text-sm font-medium text-gray-800">Problem solved</p>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($attempt['created_at'])); ?></p>
                                    </div>
                                    <span class="text-sm font-semibold text-green-600"><?php echo number_format($attempt['score'], 1); ?>%</span>
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
                        <input type="text" id="search-scenarios" placeholder="Search problem-solving scenarios..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    <select id="severity-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <option value="">All Severities</option>
                        <option value="low" <?php echo $severity_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                    <select id="difficulty-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
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
                        <i class="fas fa-puzzle-piece text-4xl text-gray-400 mb-4"></i>
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
                        
                        // Severity colors and icons
                        $severity_colors = [
                            'low' => 'bg-green-100 text-green-800',
                            'medium' => 'bg-yellow-100 text-yellow-800',
                            'high' => 'bg-orange-100 text-orange-800',
                            'critical' => 'bg-red-100 text-red-800'
                        ];
                        
                        $severity_icons = [
                            'low' => 'fas fa-info-circle',
                            'medium' => 'fas fa-exclamation-triangle',
                            'high' => 'fas fa-exclamation-circle',
                            'critical' => 'fas fa-radiation'
                        ];
                        ?>
                        
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-300 scenario-card flex flex-col" 
                             data-severity="<?php echo $scenario['severity']; ?>" 
                             data-difficulty="<?php echo $scenario['difficulty']; ?>"
                             data-title="<?php echo strtolower($scenario['title']); ?>">
                            
                            <!-- Scenario Header -->
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center">
                                        <i class="<?php echo $severity_icons[$scenario['severity']]; ?> text-orange-600 text-xl mr-3"></i>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($scenario['title']); ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo ucfirst($scenario['severity']); ?> Priority</p>
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
                                        <?php echo $scenario['time_limit']; ?> min
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Scenario Content Preview -->
                            <div class="p-6 flex-grow">
                                <div class="mb-4">
                                    <h4 class="text-sm font-medium text-gray-800 mb-2">Problem:</h4>
                                    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($scenario['description'], 0, 80) . (strlen($scenario['description']) > 80 ? '...' : '')); ?></p>
                                    
                                    <?php if ($scenario['resources']): ?>
                                        <h4 class="text-sm font-medium text-gray-800 mb-2">Available Resources:</h4>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars(substr($scenario['resources'], 0, 80) . (strlen($scenario['resources']) > 80 ? '...' : '')); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Points</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo $scenario['points']; ?></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Avg Score</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo number_format($scenario['avg_score'], 1); ?>%</p>
                                    </div>
                                </div>
                                
                                <!-- Severity Indicator -->
                                <div class="mb-4 p-3 rounded-lg <?php echo $severity_colors[$scenario['severity']]; ?>">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium"><?php echo ucfirst($scenario['severity']); ?> Priority</span>
                                        <i class="<?php echo $severity_icons[$scenario['severity']]; ?> text-lg"></i>
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
                                        <button onclick="retakeScenario(<?php echo $scenario['id']; ?>)" class="flex-1 bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors text-sm">
                                            <i class="fas fa-redo mr-2"></i>Retake
                                        </button>
                                        <button onclick="viewResults(<?php echo $scenario['id']; ?>)" class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors text-sm">
                                            <i class="fas fa-chart-bar mr-2"></i>Results
                                        </button>
                                    <?php else: ?>
                                        <button onclick="startScenario(<?php echo $scenario['id']; ?>)" class="flex-1 bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors text-sm">
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
            console.log('Document ready, initializing problem-solving filters...');
            
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

            // Severity filter
            $('#severity-filter').on('change', function() {
                console.log('Severity filter changed:', $(this).val());
                const selectedSeverity = $(this).val();
                $('.scenario-card').each(function() {
                    const severity = $(this).data('severity');
                    
                    if (selectedSeverity === '' || severity === selectedSeverity) {
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
                $('#severity-filter').val('');
                $('#difficulty-filter').val('');
                $('.scenario-card').show();
            });
            
            console.log('Problem-solving filters initialized successfully');
        });

        // Scenario action functions
        function startScenario(scenarioId) {
            // Redirect to scenario start page
            window.location.href = `problem-solving-start.php?id=${scenarioId}`;
        }

        function retakeScenario(scenarioId) {
            if (confirm('Are you sure you want to retake this scenario? Your previous score will be saved.')) {
                window.location.href = `problem-solving-start.php?id=${scenarioId}&retake=1`;
            }
        }

        function previewScenario(scenarioId) {
            // Open preview modal or redirect to preview page
            window.location.href = `problem-solving-preview.php?id=${scenarioId}`;
        }

        function viewResults(scenarioId) {
            // Redirect to results page
            window.location.href = `problem-solving-results.php?id=${scenarioId}`;
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
