<?php
require_once '../../includes/session-config.php';
session_start();
require_once '../includes/database.php';
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
    header('Location: problem-solving.php');
    exit();
}

// Fetch scenario details
try {
    $stmt = $pdo->prepare("SELECT * FROM problem_scenarios WHERE id = ?");
    $stmt->execute([$scenario_id]);
    $scenario = $stmt->fetch();

    if (!$scenario) {
        header('Location: problem-solving.php');
        exit();
    }

    // Get user's previous attempts for this scenario
    $stmt = $pdo->prepare("
        SELECT * FROM training_attempts 
        WHERE user_id = ? AND scenario_id = ? AND scenario_type = 'problem_solving'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id, $scenario_id]);
    $user_attempts = $stmt->fetchAll();

    // Get user's best score for this scenario
    $best_score = 0;
    foreach ($user_attempts as $attempt) {
        if ($attempt['score'] > $best_score) {
            $best_score = $attempt['score'];
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
    header('Location: problem-solving.php');
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
                            <a href="problem-solving.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Problem Solving Training
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
                                <div class="text-2xl font-bold text-orange-600"><?php echo $scenario['points']; ?></div>
                                <div class="text-sm text-gray-500">Points</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600"><?php echo $scenario['time_limit']; ?></div>
                                <div class="text-sm text-gray-500">Minutes</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scenario Tags -->
                <div class="flex items-center space-x-4 mb-6">
                    <span class="px-3 py-1 text-sm font-medium rounded-full 
                        <?php 
                        switch ($scenario['severity']) {
                            case 'low': echo 'bg-green-100 text-green-800'; break;
                            case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'high': echo 'bg-orange-100 text-orange-800'; break;
                            case 'critical': echo 'bg-red-100 text-red-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?php echo ucfirst($scenario['severity']); ?> Priority
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

                <!-- Scenario Resources -->
                <div class="bg-orange-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        <i class="fas fa-tools text-orange-600 mr-2"></i>
                        Available Resources
                    </h3>
                    <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($scenario['resources'])); ?></p>
                </div>
            </div>

            <!-- Statistics and Progress -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
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
                        <i class="fas fa-play-circle text-orange-600 mr-2"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <?php if ($in_progress_attempt): ?>
                            <a href="problem-solving-training.php?id=<?php echo $scenario_id; ?>&attempt_id=<?php echo $in_progress_attempt['id']; ?>" 
                               class="w-full bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 transition-colors text-center block">
                                <i class="fas fa-play mr-2"></i>
                                Continue Training
                            </a>
                        <?php else: ?>
                            <a href="problem-solving-training.php?id=<?php echo $scenario_id; ?>" 
                               class="w-full bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700 transition-colors text-center block">
                                <i class="fas fa-play mr-2"></i>
                                Start Training
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function viewAttemptHistory() {
            console.log('View attempt history clicked');
        }

        function viewResults(attemptId) {
            window.location.href = `problem-solving-results.php?attempt_id=${attemptId}`;
        }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
