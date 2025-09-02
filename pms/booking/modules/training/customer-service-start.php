<?php
require_once '../../includes/session-config.php';
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

// Get scenario ID from URL
$scenario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$scenario_id) {
    header('Location: customer-service.php');
    exit();
}

// Fetch scenario details
try {
    $stmt = $pdo->prepare("
        SELECT 
            css.*,
            COALESCE(AVG(ta.score), 0) as avg_score,
            COUNT(ta.id) as attempt_count,
            COUNT(CASE WHEN ta.status = 'completed' THEN 1 END) as completed_count
        FROM customer_service_scenarios css
        LEFT JOIN training_attempts ta ON css.id = ta.scenario_id AND ta.scenario_type = 'customer_service'
        WHERE css.id = ?
        GROUP BY css.id
    ");
    $stmt->execute([$scenario_id]);
    $scenario = $stmt->fetch();

    if (!$scenario) {
        header('Location: customer-service.php');
        exit();
    }

    // Get user's previous attempts for this scenario
    $stmt = $pdo->prepare("
        SELECT * FROM training_attempts 
        WHERE user_id = ? AND scenario_id = ? AND scenario_type = 'customer_service'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id, $scenario_id]);
    $user_attempts = $stmt->fetchAll();

    // Get user's best score for this scenario
    $best_score = 0;
    $best_attempt = null;
    foreach ($user_attempts as $attempt) {
        if ($attempt['score'] > $best_score) {
            $best_score = $attempt['score'];
            $best_attempt = $attempt;
        }
    }

    // Check if user has an in-progress attempt
    $in_progress_attempt = null;
    foreach ($user_attempts as $attempt) {
        if ($attempt['status'] === 'in_progress') {
            $in_progress_attempt = $attempt;
            break;
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching scenario: " . $e->getMessage());
    header('Location: customer-service.php');
    exit();
}

// Set page title
$page_title = 'Start Training - ' . $scenario['title'];

// Include unified header and sidebar
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <!-- Breadcrumb -->
            <div class="mb-6">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="customer-service.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Customer Service Training
                            </a>
                        </li>
                    </ol>
                </nav>
            </div>

            <!-- Scenario Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($scenario['title']); ?></h1>
                        <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($scenario['description']); ?></p>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600"><?php echo $scenario['points']; ?></div>
                                <div class="text-sm text-gray-500">Points</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600"><?php echo $scenario['estimated_time']; ?></div>
                                <div class="text-sm text-gray-500">Minutes</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scenario Tags -->
                <div class="flex items-center space-x-4 mb-6">
                    <span class="px-3 py-1 text-sm font-medium rounded-full 
                        <?php 
                        switch ($scenario['type']) {
                            case 'complaints': echo 'bg-red-100 text-red-800'; break;
                            case 'requests': echo 'bg-blue-100 text-blue-800'; break;
                            case 'emergencies': echo 'bg-orange-100 text-orange-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?php echo ucfirst($scenario['type']); ?>
                    </span>
                    <span class="px-3 py-1 text-sm font-medium rounded-full 
                        <?php 
                        switch ($scenario['difficulty']) {
                            case 'beginner': echo 'bg-green-100 text-green-800'; break;
                            case 'intermediate': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'advanced': echo 'bg-red-100 text-red-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?php echo ucfirst($scenario['difficulty']); ?>
                    </span>
                </div>

                <!-- Scenario Details -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                            Situation
                        </h3>
                        <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($scenario['situation'])); ?></p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">
                            <i class="fas fa-user text-blue-600 mr-2"></i>
                            Guest Request
                        </h3>
                        <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($scenario['guest_request'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Statistics and Progress -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Global Statistics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-chart-bar text-blue-600 mr-2"></i>
                        Global Statistics
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Attempts:</span>
                            <span class="font-semibold"><?php echo $scenario['attempt_count']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Completed:</span>
                            <span class="font-semibold"><?php echo $scenario['completed_count']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Average Score:</span>
                            <span class="font-semibold"><?php echo number_format($scenario['avg_score'] ?? 0, 1); ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- Your Progress -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-user-graduate text-green-600 mr-2"></i>
                        Your Progress
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Attempts:</span>
                            <span class="font-semibold"><?php echo count($user_attempts); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Best Score:</span>
                            <span class="font-semibold text-green-600"><?php echo $best_score > 0 ? number_format($best_score, 1) . '%' : 'Not attempted'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span class="font-semibold">
                                <?php 
                                if ($in_progress_attempt) {
                                    echo '<span class="text-yellow-600">In Progress</span>';
                                } elseif ($best_score >= 80) {
                                    echo '<span class="text-green-600">Mastered</span>';
                                } elseif ($best_score > 0) {
                                    echo '<span class="text-blue-600">Attempted</span>';
                                } else {
                                    echo '<span class="text-gray-600">Not Started</span>';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-play-circle text-purple-600 mr-2"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <?php if ($in_progress_attempt): ?>
                            <a href="customer-service-training.php?id=<?php echo $scenario_id; ?>&attempt_id=<?php echo $in_progress_attempt['id']; ?>" 
                               class="w-full bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 transition-colors text-center block">
                                <i class="fas fa-play mr-2"></i>
                                Continue Training
                            </a>
                        <?php else: ?>
                            <a href="customer-service-training.php?id=<?php echo $scenario_id; ?>" 
                               class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors text-center block">
                                <i class="fas fa-play mr-2"></i>
                                Start Training
                            </a>
                        <?php endif; ?>
                        
                        <?php if (count($user_attempts) > 0): ?>
                            <button onclick="viewAttemptHistory()" 
                                    class="w-full bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition-colors">
                                <i class="fas fa-history mr-2"></i>
                                View History
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Attempt History (if any) -->
            <?php if (count($user_attempts) > 0): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-history text-gray-600 mr-2"></i>
                    Your Attempt History
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($user_attempts as $attempt): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y H:i', strtotime($attempt['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                        if ($attempt['score'] >= 80) echo 'bg-green-100 text-green-800';
                                        elseif ($attempt['score'] >= 60) echo 'bg-yellow-100 text-yellow-800';
                                        else echo 'bg-red-100 text-red-800';
                                        ?>">
                                        <?php echo number_format($attempt['score'] ?? 0, 1); ?>%
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $attempt['duration_minutes']; ?> min
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                        switch ($attempt['status']) {
                                            case 'completed': echo 'bg-green-100 text-green-800'; break;
                                            case 'in_progress': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'abandoned': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $attempt['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($attempt['status'] === 'in_progress'): ?>
                                        <a href="customer-service-training.php?id=<?php echo $scenario_id; ?>&attempt_id=<?php echo $attempt['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">Continue</a>
                                    <?php elseif ($attempt['status'] === 'completed'): ?>
                                        <button onclick="viewResults(<?php echo $attempt['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900">View Results</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        function viewAttemptHistory() {
            // Scroll to attempt history section
            document.querySelector('.bg-white.rounded-lg.shadow-md.p-6:last-child').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        function viewResults(attemptId) {
            // Redirect to results page
            window.location.href = `customer-service-results.php?attempt_id=${attemptId}`;
        }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
