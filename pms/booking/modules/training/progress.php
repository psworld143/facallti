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

// Fetch comprehensive training progress data
try {
    // Get overall training statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_attempts,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_scenarios,
            AVG(CASE WHEN status = 'completed' THEN score END) as avg_score,
            SUM(CASE WHEN status = 'completed' THEN duration_minutes END) as total_time,
            COUNT(CASE WHEN score >= 90 THEN 1 END) as excellent_count,
            COUNT(CASE WHEN score >= 80 AND score < 90 THEN 1 END) as good_count,
            COUNT(CASE WHEN score >= 70 AND score < 80 THEN 1 END) as satisfactory_count,
            COUNT(CASE WHEN score < 70 THEN 1 END) as needs_improvement_count
        FROM training_attempts 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $overall_stats = $stmt->fetch();

    // Get progress by scenario type
    $stmt = $pdo->prepare("
        SELECT 
            scenario_type,
            COUNT(*) as attempts,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
            AVG(CASE WHEN status = 'completed' THEN score END) as avg_score,
            SUM(CASE WHEN status = 'completed' THEN duration_minutes END) as total_time
        FROM training_attempts 
        WHERE user_id = ?
        GROUP BY scenario_type
    ");
    $stmt->execute([$user_id]);
    $progress_by_type = $stmt->fetchAll();

    // Get recent training activity
    $stmt = $pdo->prepare("
        SELECT 
            ta.*,
            CASE 
                WHEN ta.scenario_type = 'scenario' THEN ts.title
                WHEN ta.scenario_type = 'customer_service' THEN css.title
                WHEN ta.scenario_type = 'problem_solving' THEN ps.title
                ELSE 'Unknown Scenario'
            END as scenario_title,
            ta.scenario_type
        FROM training_attempts ta
        LEFT JOIN training_scenarios ts ON ta.scenario_id = ts.id AND ta.scenario_type = 'scenario'
        LEFT JOIN customer_service_scenarios css ON ta.scenario_id = css.id AND ta.scenario_type = 'customer_service'
        LEFT JOIN problem_scenarios ps ON ta.scenario_id = ps.id AND ta.scenario_type = 'problem_solving'
        WHERE ta.user_id = ?
        ORDER BY ta.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll();

    // Get performance trends (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as attempts,
            AVG(score) as avg_score
        FROM training_attempts 
        WHERE user_id = ? AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$user_id]);
    $performance_trends = $stmt->fetchAll();

    // Get achievements and milestones
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN scenario_type = 'scenario' THEN 1 END) as training_scenarios_completed,
            COUNT(CASE WHEN scenario_type = 'customer_service' THEN 1 END) as customer_service_completed,
            COUNT(CASE WHEN scenario_type = 'problem_solving' THEN 1 END) as problem_solving_completed,
            COUNT(CASE WHEN score >= 95 THEN 1 END) as perfect_scores,
            COUNT(CASE WHEN score >= 90 THEN 1 END) as excellent_scores,
            COUNT(CASE WHEN score >= 80 THEN 1 END) as good_scores
        FROM training_attempts 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $achievements = $stmt->fetch();

    // Get learning path progress
    $stmt = $pdo->prepare("
        SELECT 
            'Training Scenarios' as category,
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'scenario'
        UNION ALL
        SELECT 
            'Customer Service' as category,
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'customer_service'
        UNION ALL
        SELECT 
            'Problem Solving' as category,
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
        FROM training_attempts 
        WHERE user_id = ? AND scenario_type = 'problem_solving'
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $learning_path = $stmt->fetchAll();

    // Calculate overall completion percentage
    $total_scenarios = $overall_stats['total_attempts'];
    $completed_scenarios = $overall_stats['completed_scenarios'];
    $completion_percentage = $total_scenarios > 0 ? ($completed_scenarios / $total_scenarios) * 100 : 0;

} catch (PDOException $e) {
    error_log("Error fetching training progress: " . $e->getMessage());
    $overall_stats = [
        'total_attempts' => 0,
        'completed_scenarios' => 0,
        'avg_score' => 0,
        'total_time' => 0,
        'excellent_count' => 0,
        'good_count' => 0,
        'satisfactory_count' => 0,
        'needs_improvement_count' => 0
    ];
    $progress_by_type = [];
    $recent_activity = [];
    $performance_trends = [];
    $achievements = [
        'training_scenarios_completed' => 0,
        'customer_service_completed' => 0,
        'problem_solving_completed' => 0,
        'perfect_scores' => 0,
        'excellent_scores' => 0,
        'good_scores' => 0
    ];
    $learning_path = [];
    $completion_percentage = 0;
}

// Set page title for unified header
$page_title = 'Training Progress';

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
                        <h2 class="text-2xl font-bold text-gray-800">Your Learning Journey</h2>
                        <p class="text-gray-600 mt-1">Track your progress, achievements, and performance across all training modules</p>
                    </div>
                    <div class="flex space-x-3">
                        <button class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-certificate mr-2"></i>View Certificates
                        </button>
                    </div>
                </div>
            </div>

            <!-- Overall Progress Overview -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Overall Progress</h3>
                    <span class="text-2xl font-bold text-purple-600"><?php echo number_format($completion_percentage, 1); ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 mb-4">
                    <div class="bg-gradient-to-r from-purple-500 to-indigo-600 h-3 rounded-full transition-all duration-500" style="width: <?php echo $completion_percentage; ?>%"></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <p class="text-sm text-gray-600">Total Attempts</p>
                        <p class="text-xl font-bold text-gray-900"><?php echo $overall_stats['total_attempts']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Completed</p>
                        <p class="text-xl font-bold text-green-600"><?php echo $overall_stats['completed_scenarios']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Average Score</p>
                        <p class="text-xl font-bold text-blue-600"><?php echo number_format($overall_stats['avg_score'] ?? 0, 1); ?>%</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Training Time</p>
                        <p class="text-xl font-bold text-purple-600"><?php echo round($overall_stats['total_time'] / 60, 1); ?>h</p>
                    </div>
                </div>
            </div>

            <!-- Performance Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-crown text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Excellent (90%+)</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['excellent_count']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-star text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Good (80-89%)</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['good_count']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <i class="fas fa-check text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Satisfactory (70-79%)</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['satisfactory_count']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-red-100 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Needs Improvement</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['needs_improvement_count']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress by Training Type -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <?php foreach ($progress_by_type as $progress): ?>
                    <?php 
                    $type_icons = [
                        'scenario' => 'fas fa-play-circle',
                        'customer_service' => 'fas fa-headset',
                        'problem_solving' => 'fas fa-puzzle-piece'
                    ];
                    $type_colors = [
                        'scenario' => 'bg-purple-100 text-purple-600',
                        'customer_service' => 'bg-blue-100 text-blue-600',
                        'problem_solving' => 'bg-orange-100 text-orange-600'
                    ];
                    $type_names = [
                        'scenario' => 'Training Scenarios',
                        'customer_service' => 'Customer Service',
                        'problem_solving' => 'Problem Solving'
                    ];
                    $completion_rate = $progress['attempts'] > 0 ? ($progress['completed'] / $progress['attempts']) * 100 : 0;
                    ?>
                    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                        <div class="flex items-center mb-4">
                            <div class="p-3 rounded-lg <?php echo $type_colors[$progress['scenario_type']]; ?>">
                                <i class="<?php echo $type_icons[$progress['scenario_type']]; ?> text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-lg font-semibold text-gray-800"><?php echo $type_names[$progress['scenario_type']]; ?></h4>
                                <p class="text-sm text-gray-500"><?php echo $progress['completed']; ?> of <?php echo $progress['attempts']; ?> completed</p>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mb-3">
                            <div class="bg-gradient-to-r from-purple-500 to-indigo-600 h-2 rounded-full" style="width: <?php echo $completion_rate; ?>%"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-600">Avg Score</p>
                                <p class="font-semibold text-gray-900"><?php echo number_format($progress['avg_score'] ?? 0, 1); ?>%</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Time</p>
                                <p class="font-semibold text-gray-900"><?php echo round($progress['total_time'] / 60, 1); ?>h</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Achievements and Milestones -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Achievements -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Achievements</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-trophy text-yellow-500 text-xl mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-800">Perfect Scores</p>
                                    <p class="text-sm text-gray-600">95% or higher</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-green-600"><?php echo $achievements['perfect_scores']; ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-medal text-blue-500 text-xl mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-800">Excellent Performance</p>
                                    <p class="text-sm text-gray-600">90% or higher</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-blue-600"><?php echo $achievements['excellent_scores']; ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-star text-purple-500 text-xl mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-800">Good Performance</p>
                                    <p class="text-sm text-gray-600">80% or higher</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-purple-600"><?php echo $achievements['good_scores']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Learning Path Progress -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Learning Path Progress</h3>
                    <div class="space-y-4">
                        <?php foreach ($learning_path as $path): ?>
                            <?php 
                            $path_completion = $path['total'] > 0 ? ($path['completed'] / $path['total']) * 100 : 0;
                            $path_icons = [
                                'Training Scenarios' => 'fas fa-play-circle text-purple-500',
                                'Customer Service' => 'fas fa-headset text-blue-500',
                                'Problem Solving' => 'fas fa-puzzle-piece text-orange-500'
                            ];
                            ?>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center">
                                        <i class="<?php echo $path_icons[$path['category']]; ?> text-xl mr-3"></i>
                                        <span class="font-medium text-gray-800"><?php echo $path['category']; ?></span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-600"><?php echo $path['completed']; ?>/<?php echo $path['total']; ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-purple-500 to-indigo-600 h-2 rounded-full transition-all duration-500" style="width: <?php echo $path_completion; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity and Performance Trends -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        <?php if (empty($recent_activity)): ?>
                            <p class="text-sm text-gray-500">No recent activity</p>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-play text-white text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($activity['scenario_title']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php 
                                            if ($activity['score'] >= 90) echo 'bg-green-100 text-green-800';
                                            elseif ($activity['score'] >= 80) echo 'bg-blue-100 text-blue-800';
                                            elseif ($activity['score'] >= 70) echo 'bg-yellow-100 text-yellow-800';
                                            else echo 'bg-red-100 text-red-800';
                                            ?>">
                                            <?php echo number_format($activity['score'] ?? 0, 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Performance Trends -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Performance Trends (Last 30 Days)</h3>
                    <?php if (empty($performance_trends)): ?>
                        <p class="text-sm text-gray-500">No performance data available</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($performance_trends, 0, 7) as $trend): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600"><?php echo date('M j', strtotime($trend['date'])); ?></span>
                                    <div class="flex items-center space-x-4">
                                        <span class="text-sm text-gray-500"><?php echo $trend['attempts']; ?> attempts</span>
                                        <span class="text-sm font-semibold text-gray-800"><?php echo number_format($trend['avg_score'] ?? 0, 1); ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Next Steps</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button onclick="window.location.href='scenarios.php'" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                        <i class="fas fa-play-circle text-purple-600 text-xl mr-3"></i>
                        <div class="text-left">
                            <span class="font-medium text-purple-800">Continue Training</span>
                            <p class="text-sm text-purple-600">Start new scenarios</p>
                        </div>
                    </button>
                    <button onclick="window.location.href='certificates.php'" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                        <i class="fas fa-certificate text-green-600 text-xl mr-3"></i>
                        <div class="text-left">
                            <span class="font-medium text-green-800">View Certificates</span>
                            <p class="text-sm text-green-600">Download achievements</p>
                        </div>
                    </button>
                    <button onclick="window.location.href='leaderboard.php'" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                        <i class="fas fa-trophy text-blue-600 text-xl mr-3"></i>
                        <div class="text-left">
                            <span class="font-medium text-blue-800">Leaderboard</span>
                            <p class="text-sm text-blue-600">Compare performance</p>
                        </div>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
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

        // Animate progress bars on page load
        $(document).ready(function() {
            $('.bg-gradient-to-r').each(function() {
                const width = $(this).css('width');
                $(this).css('width', '0%');
                setTimeout(() => {
                    $(this).css('width', width);
                }, 500);
            });
        });
    </script>
</body>
</html>
