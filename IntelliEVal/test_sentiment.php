<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/data_analysis.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../index.php');
    exit();
}

// Initialize data analysis
$dataAnalysis = new DataAnalysis($conn);

// Test some sample texts
$test_texts = [
    "The teacher is excellent and explains everything clearly.",
    "This teacher is terrible and doesn't know how to teach.",
    "The class is okay, nothing special.",
    "I love this teacher's teaching style!",
    "I hate this subject and the teacher is bad."
];

echo "<h1>Sentiment Analysis Test</h1>";
echo "<p>Testing VADER sentiment analysis with sample texts:</p>";

foreach ($test_texts as $text) {
    $sentiment = $dataAnalysis->analyzeSentiment($text);
    echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
    echo "<strong>Text:</strong> " . htmlspecialchars($text) . "<br>";
    echo "<strong>Sentiment:</strong> " . ucfirst($sentiment['sentiment']) . "<br>";
    echo "<strong>Compound Score:</strong> " . round($sentiment['compound'], 3) . "<br>";
    echo "<strong>Positive:</strong> " . round($sentiment['positive'], 3) . "<br>";
    echo "<strong>Negative:</strong> " . round($sentiment['negative'], 3) . "<br>";
    echo "<strong>Neutral:</strong> " . round($sentiment['neutral'], 3) . "<br>";
    echo "</div>";
}

// Test with actual feedback data
echo "<h2>Testing with Actual Feedback Data</h2>";

$feedback_query = "SELECT 
    er.id,
    er.text_response,
    er.rating_value
FROM evaluation_responses er
JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
WHERE er.text_response IS NOT NULL AND er.text_response != ''
AND es.evaluatee_type = 'teacher'
LIMIT 5";

$feedback_result = mysqli_query($conn, $feedback_query);
if ($feedback_result) {
    while ($row = mysqli_fetch_assoc($feedback_result)) {
        $sentiment = $dataAnalysis->analyzeSentiment($row['text_response']);
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<strong>Feedback:</strong> " . htmlspecialchars(html_entity_decode($row['text_response'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . "<br>";
        echo "<strong>Rating:</strong> " . $row['rating_value'] . "<br>";
        echo "<strong>Sentiment:</strong> " . ucfirst($sentiment['sentiment']) . "<br>";
        echo "<strong>Compound Score:</strong> " . round($sentiment['compound'], 3) . "<br>";
        echo "</div>";
    }
}

echo "<p><a href='advanced_analytics.php'>Back to Advanced Analytics</a></p>";
?>
