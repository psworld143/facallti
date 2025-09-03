<?php
require_once "../../includes/session-config.php";
session_start();
require_once "../../../includes/database.php";
require_once "../../includes/functions.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"];

// Get scenario ID from URL
$scenario_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$attempt_id = isset($_GET["attempt_id"]) ? (int)$_GET["attempt_id"] : 0;

if (!$scenario_id) {
    header("Location: problem-solving.php");
    exit();
}

// Fetch scenario details
try {
    $stmt = $pdo->prepare("SELECT * FROM problem_scenarios WHERE id = ?");
    $stmt->execute([$scenario_id]);
    $scenario = $stmt->fetch();

    if (!$scenario) {
        header("Location: problem-solving.php");
        exit();
    }

    // Fetch questions for this scenario
    $stmt = $pdo->prepare("
        SELECT sq.*, GROUP_CONCAT(
            CONCAT(qo.option_value, \":\", qo.option_text) 
            ORDER BY qo.option_order 
            SEPARATOR \"|\"
        ) as options
        FROM scenario_questions sq
        LEFT JOIN question_options qo ON sq.id = qo.question_id
        WHERE sq.scenario_id = ?
        GROUP BY sq.id
        ORDER BY sq.question_order
    ");
    $stmt->execute([$scenario_id]);
    $questions = $stmt->fetchAll();

    // Check if questions were found
    if (empty($questions)) {
        error_log("No questions found for scenario ID: " . $scenario_id);
        header("Location: problem-solving.php");
        exit();
    }

    // Process questions to create options array
    foreach ($questions as &$question) {
        $options_array = [];
        $options_string = $question["options"];
        
        if ($options_string) {
            $option_pairs = explode("|", $options_string);
            
            foreach ($option_pairs as $pair) {
                $parts = explode(":", $pair, 2);
                if (count($parts) == 2) {
                    $options_array[$parts[0]] = $parts[1];
                }
            }
        }
        
        $question["options_array"] = $options_array;
    }

    // Handle attempt creation or retrieval
    if ($attempt_id) {
        // Continue existing attempt
        $stmt = $pdo->prepare("
            SELECT * FROM training_attempts 
            WHERE id = ? AND user_id = ? AND scenario_id = ? AND scenario_type = \"problem_solving\"
        ");
        $stmt->execute([$attempt_id, $user_id, $scenario_id]);
        $attempt = $stmt->fetch();
        
        if (!$attempt) {
            header("Location: problem-solving-start.php?id=" . $scenario_id);
            exit();
        }
        
        $answers = $attempt["answers"] ? json_decode($attempt["answers"], true) : [];
        $current_question = isset($_GET["question"]) ? (int)$_GET["question"] : 1;
    } else {
        // Create new attempt
        $stmt = $pdo->prepare("
            INSERT INTO training_attempts (user_id, scenario_id, scenario_type, status, created_at)
            VALUES (?, ?, \"problem_solving\", \"in_progress\", NOW())
        ");
        $stmt->execute([$user_id, $scenario_id]);
        $attempt_id = $pdo->lastInsertId();
        
        $answers = [];
        $current_question = 1;
    }

} catch (PDOException $e) {
    error_log("Error in training page: " . $e->getMessage());
    header("Location: problem-solving.php");
    exit();
}

// Set page title
$page_title = "Training - " . $scenario["title"];

// Include unified header and sidebar
include "../../includes/header-unified.php";
include "../../includes/sidebar-unified.php";
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <!-- Progress Bar -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <?php echo htmlspecialchars($scenario["title"]); ?>
                    </h2>
                    <div class="text-sm text-gray-600">
                        Question <?php echo $current_question; ?> of <?php echo count($questions); ?>
                    </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                         style="width: <?php echo count($questions) > 0 ? ($current_question - 1) / count($questions) * 100 : 0; ?>%"></div>
                </div>
            </div>

            <!-- Question Container -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <?php if ($current_question <= count($questions)): ?>
                    <?php $question = $questions[$current_question - 1]; ?>
                    
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">
                            Question <?php echo $current_question; ?>
                        </h3>
                        <p class="text-lg text-gray-700 leading-relaxed">
                            <?php echo htmlspecialchars($question["question"]); ?>
                        </p>
                    </div>

                    <form id="questionForm" method="POST" action="process-training-answer.php">
                        <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                        <input type="hidden" name="scenario_id" value="<?php echo $scenario_id; ?>">
                        <input type="hidden" name="question_number" value="<?php echo $current_question; ?>">
                        <input type="hidden" name="question_id" value="<?php echo $question["id"]; ?>">
                        <input type="hidden" name="scenario_type" value="problem_solving">
                        
                        <div class="space-y-4">
                            <?php foreach ($question["options_array"] as $value => $text): ?>
                                <label class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                                    <input type="radio" name="answer" value="<?php echo $value; ?>" 
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                           <?php echo (isset($answers[$current_question]) && $answers[$current_question] === $value) ? "checked" : ""; ?>>
                                    <span class="ml-3 text-gray-700">
                                        <span class="font-medium"><?php echo $value; ?>.</span>
                                        <?php echo htmlspecialchars($text); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex justify-between mt-8">
                            <?php if ($current_question > 1): ?>
                                <button type="button" onclick="previousQuestion()" 
                                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Previous
                                </button>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>

                            <?php if ($current_question < count($questions)): ?>
                                <button type="submit" 
                                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                    Next
                                    <i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            <?php else: ?>
                                <button type="submit" 
                                        class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                    <i class="fas fa-check mr-2"></i>
                                    Complete Training
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- Training Complete -->
                    <div class="text-center py-12">
                        <div class="text-6xl text-green-500 mb-4">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Training Complete!</h3>
                        <p class="text-gray-600 mb-6">
                            You have completed all questions for this scenario. Click below to view your results.
                        </p>
                        <a href="problem-solving-results.php?attempt_id=<?php echo $attempt_id; ?>" 
                           class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-chart-bar mr-2"></i>
                            View Results
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Scenario Context -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>
                    Scenario Context
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <strong>Description:</strong> <?php echo htmlspecialchars($scenario["description"]); ?>
                    </div>
                    <div>
                        <strong>Resources:</strong> <?php echo htmlspecialchars($scenario["resources"]); ?>
                    </div>
                    <div>
                        <strong>Severity:</strong> 
                        <span class="px-2 py-1 text-xs rounded-full 
                            <?php 
                            switch($scenario["severity"]) {
                                case "low": echo "bg-green-100 text-green-800"; break;
                                case "medium": echo "bg-yellow-100 text-yellow-800"; break;
                                case "high": echo "bg-red-100 text-red-800"; break;
                                default: echo "bg-gray-100 text-gray-800";
                            }
                            ?>">
                            <?php echo ucfirst($scenario["severity"]); ?>
                        </span>
                    </div>
                    <div>
                        <strong>Difficulty:</strong> 
                        <span class="px-2 py-1 text-xs rounded-full 
                            <?php 
                            switch($scenario["difficulty"]) {
                                case "beginner": echo "bg-green-100 text-green-800"; break;
                                case "intermediate": echo "bg-yellow-100 text-yellow-800"; break;
                                case "advanced": echo "bg-red-100 text-red-800"; break;
                                default: echo "bg-gray-100 text-gray-800";
                            }
                            ?>">
                            <?php echo ucfirst($scenario["difficulty"]); ?>
                        </span>
                    </div>
                </div>
            </div>
        </main>

        <script>
        function previousQuestion() {
            const currentQuestion = <?php echo $current_question; ?>;
            if (currentQuestion > 1) {
                window.location.href = `problem-solving-training.php?id=<?php echo $scenario_id; ?>&attempt_id=<?php echo $attempt_id; ?>&question=${currentQuestion - 1}`;
            }
        }
        </script>

<?php include "../../includes/footer.php"; ?>
