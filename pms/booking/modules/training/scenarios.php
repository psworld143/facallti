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
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

// Fetch training scenarios from database
try {
    // Get training scenarios with filters
    $where_conditions = ["1=1"];
    $params = [];
    
    if (!empty($category_filter)) {
        $where_conditions[] = "category = ?";
        $params[] = $category_filter;
    }
    
    if (!empty($difficulty_filter)) {
        $where_conditions[] = "difficulty = ?";
        $params[] = $difficulty_filter;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            ts.*,
            COUNT(sq.id) as question_count,
            COALESCE(AVG(ta.score), 0) as avg_score,
            COUNT(ta.id) as attempt_count
        FROM training_scenarios ts
        LEFT JOIN scenario_questions sq ON ts.id = sq.scenario_id
        LEFT JOIN training_attempts ta ON ts.id = ta.scenario_id AND ta.scenario_type = 'scenario'
        WHERE {$where_clause}
        GROUP BY ts.id
        ORDER BY ts.difficulty, ts.title
    ");
    $stmt->execute($params);
    $scenarios = $stmt->fetchAll();

    // Get user's completed scenarios
    $stmt = $pdo->prepare("
        SELECT scenario_id, score, status, created_at
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'scenario'
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
            COUNT(CASE WHEN difficulty = 'beginner' THEN 1 END) as beginner_count,
            COUNT(CASE WHEN difficulty = 'intermediate' THEN 1 END) as intermediate_count,
            COUNT(CASE WHEN difficulty = 'advanced' THEN 1 END) as advanced_count,
            COUNT(CASE WHEN category = 'front_desk' THEN 1 END) as front_desk_count,
            COUNT(CASE WHEN category = 'housekeeping' THEN 1 END) as housekeeping_count,
            COUNT(CASE WHEN category = 'management' THEN 1 END) as management_count
        FROM training_scenarios
    ");
    $stmt->execute();
    $stats = $stmt->fetch();

    // Get user's training statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as completed_scenarios,
            AVG(score) as avg_score,
            SUM(duration_minutes) as total_time
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'scenario' AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $user_stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Error fetching training scenarios: " . $e->getMessage());
    $scenarios = [];
    $user_attempts = [];
    $user_attempts_map = [];
    $stats = [
        'total_scenarios' => 0,
        'beginner_count' => 0,
        'intermediate_count' => 0,
        'advanced_count' => 0,
        'front_desk_count' => 0,
        'housekeeping_count' => 0,
        'management_count' => 0
    ];
    $user_stats = [
        'completed_scenarios' => 0,
        'avg_score' => 0,
        'total_time' => 0
    ];
}

// Set page title for unified header
$page_title = 'Training Scenarios';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Training Scenarios</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Available Scenarios</h2>
                        <p class="text-gray-600 mt-1">Choose from a variety of training scenarios to improve your skills</p>
                    </div>
                    <div class="flex space-x-3">
                        <button class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-random mr-2"></i>Random Scenario
                        </button>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Download Certificate
                        </button>
                    </div>
                </div>
            </div>

            <!-- User Progress Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
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
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Training Time</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo round($user_stats['total_time'] / 60, 1); ?>h</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" id="search-scenarios" placeholder="Search scenarios..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <select id="category-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">All Categories</option>
                        <option value="front_desk" <?php echo $category_filter === 'front_desk' ? 'selected' : ''; ?>>Front Desk</option>
                        <option value="housekeeping" <?php echo $category_filter === 'housekeeping' ? 'selected' : ''; ?>>Housekeeping</option>
                        <option value="management" <?php echo $category_filter === 'management' ? 'selected' : ''; ?>>Management</option>
                    </select>
                    <select id="difficulty-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
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
                        <i class="fas fa-play-circle text-4xl text-gray-400 mb-4"></i>
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
                        
                        // Category icons
                        $category_icons = [
                            'front_desk' => 'fas fa-user-tie',
                            'housekeeping' => 'fas fa-broom',
                            'management' => 'fas fa-chart-line'
                        ];
                        ?>
                        
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-300 scenario-card flex flex-col" 
                             data-category="<?php echo $scenario['category']; ?>" 
                             data-difficulty="<?php echo $scenario['difficulty']; ?>"
                             data-title="<?php echo strtolower($scenario['title']); ?>">
                            
                            <!-- Scenario Header -->
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center">
                                        <i class="<?php echo $category_icons[$scenario['category']]; ?> text-purple-600 text-xl mr-3"></i>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($scenario['title']); ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo ucfirst($scenario['category']); ?></p>
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
                            
                            <!-- Scenario Stats -->
                            <div class="p-6 flex-grow">
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Questions</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo $scenario['question_count']; ?></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Points</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo $scenario['points']; ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($is_completed): ?>
                                    <div class="mb-4 p-3 bg-green-50 rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-green-800">Best Score</span>
                                            <span class="text-lg font-bold text-green-600"><?php echo number_format($best_score, 1); ?>%</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Empty space to maintain consistent height -->
                                    <div class="mb-4 p-3 bg-transparent rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-transparent">Best Score</span>
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
                                        <button onclick="startScenario(<?php echo $scenario['id']; ?>)" class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors text-sm">
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
            console.log('Document ready, initializing filters...');
            
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

            // Category filter
            $('#category-filter').on('change', function() {
                console.log('Category filter changed:', $(this).val());
                const selectedCategory = $(this).val();
                $('.scenario-card').each(function() {
                    const category = $(this).data('category');
                    
                    if (selectedCategory === '' || category === selectedCategory) {
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
                $('#category-filter').val('');
                $('#difficulty-filter').val('');
                $('.scenario-card').show();
            });
            
            console.log('Filters initialized successfully');
        });

        // Scenario action functions
        function startScenario(scenarioId) {
            // Redirect to scenario start page
            window.location.href = `scenario-start.php?id=${scenarioId}`;
        }

        function retakeScenario(scenarioId) {
            if (confirm('Are you sure you want to retake this scenario? Your previous score will be saved.')) {
                window.location.href = `scenario-start.php?id=${scenarioId}&retake=1`;
            }
        }

        function previewScenario(scenarioId) {
            // Open preview modal or redirect to preview page
            window.location.href = `scenario-preview.php?id=${scenarioId}`;
        }

        function viewResults(scenarioId) {
            // Redirect to results page
            window.location.href = `scenario-results.php?id=${scenarioId}`;
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
