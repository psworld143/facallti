<?php
require_once "../../includes/session-config.php";
session_start();
require_once '../includes/database.php';
require_once "../../includes/functions.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: training-dashboard.php");
    exit();
}

// Get form data
$attempt_id = isset($_POST["attempt_id"]) ? (int)$_POST["attempt_id"] : 0;
$scenario_id = isset($_POST["scenario_id"]) ? (int)$_POST["scenario_id"] : 0;
$question_number = isset($_POST["question_number"]) ? (int)$_POST["question_number"] : 0;
$question_id = isset($_POST["question_id"]) ? (int)$_POST["question_id"] : 0;
$answer = isset($_POST["answer"]) ? $_POST["answer"] : "";
$scenario_type = isset($_POST["scenario_type"]) ? $_POST["scenario_type"] : "";

if (!$attempt_id || !$scenario_id || !$question_number || !$question_id || !$answer || !$scenario_type) {
    header("Location: training-dashboard.php");
    exit();
}

try {
    // Verify the attempt belongs to the user
    $stmt = $pdo->prepare("
        SELECT * FROM training_attempts 
        WHERE id = ? AND user_id = ? AND scenario_id = ? AND scenario_type = ?
    ");
    $stmt->execute([$attempt_id, $user_id, $scenario_id, $scenario_type]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        header("Location: training-dashboard.php");
        exit();
    }

    // Get current answers
    $answers = $attempt["answers"] ? json_decode($attempt["answers"], true) : [];
    
    // Update answers
    $answers[$question_number] = $answer;
    
    // Check if this is the last question
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_questions FROM scenario_questions WHERE scenario_id = ?");
    $stmt->execute([$scenario_id]);
    $total_questions = $stmt->fetch()["total_questions"];
    
    $status = "in_progress";
    $completed_at = null;
    
    if ($question_number >= $total_questions) {
        // Training is complete
        $status = "completed";
        $completed_at = date("Y-m-d H:i:s");
        
        // Calculate score
        $correct_answers = 0;
        $stmt = $pdo->prepare("
            SELECT sq.correct_answer, sq.question_order
            FROM scenario_questions sq
            WHERE sq.scenario_id = ?
            ORDER BY sq.question_order
        ");
        $stmt->execute([$scenario_id]);
        $correct_answers_data = $stmt->fetchAll();
        
        foreach ($correct_answers_data as $correct_data) {
            $question_num = $correct_data["question_order"];
            if (isset($answers[$question_num]) && $answers[$question_num] === $correct_data["correct_answer"]) {
                $correct_answers++;
            }
        }
        
        $score = ($correct_answers / $total_questions) * 100;
    } else {
        $score = null;
    }
    
    // Update the attempt
    $stmt = $pdo->prepare("
        UPDATE training_attempts 
        SET answers = ?, status = ?, score = ?, completed_at = ?
        WHERE id = ?
    ");
    $stmt->execute([
        json_encode($answers),
        $status,
        $score,
        $completed_at,
        $attempt_id
    ]);
    
    // Redirect based on scenario type and completion status
    if ($status === "completed") {
        // Training completed, redirect to results
        switch ($scenario_type) {
            case "customer_service":
                header("Location: customer-service-results.php?attempt_id=" . $attempt_id);
                break;
            case "problem_solving":
                header("Location: problem-solving-results.php?attempt_id=" . $attempt_id);
                break;
            case "scenario":
                header("Location: scenario-results.php?attempt_id=" . $attempt_id);
                break;
            default:
                header("Location: training-dashboard.php");
        }
    } else {
        // Continue to next question
        $next_question = $question_number + 1;
        switch ($scenario_type) {
            case "customer_service":
                header("Location: customer-service-training.php?id=" . $scenario_id . "&attempt_id=" . $attempt_id . "&question=" . $next_question);
                break;
            case "problem_solving":
                header("Location: problem-solving-training.php?id=" . $scenario_id . "&attempt_id=" . $attempt_id . "&question=" . $next_question);
                break;
            case "scenario":
                header("Location: scenario-training.php?id=" . $scenario_id . "&attempt_id=" . $attempt_id . "&question=" . $next_question);
                break;
            default:
                header("Location: training-dashboard.php");
        }
    }

} catch (PDOException $e) {
    error_log("Error processing training answer: " . $e->getMessage());
    header("Location: training-dashboard.php");
    exit();
}
?>
