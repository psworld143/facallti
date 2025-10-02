<?php
/**
 * Database Check Script: Verify Question Types
 * 
 * This script checks the current state of question types in the database
 * and shows which questions need to be updated from short_answer to fill_in_blank
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access. Admin privileges required.');
}

// Set content type for better display
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Question Types</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-6xl mx-auto px-4">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Database Question Types Check</h1>
            
            <?php
            try {
                // Get question type counts
                $count_query = "SELECT 
                    question_type,
                    COUNT(*) as count
                FROM quiz_questions 
                GROUP BY question_type 
                ORDER BY question_type";
                $count_result = mysqli_query($conn, $count_query);
                
                if (!$count_result) {
                    throw new Exception("Error getting question counts: " . mysqli_error($conn));
                }
                
                echo '<div class="mb-8">';
                echo '<h2 class="text-xl font-semibold text-gray-700 mb-4">Question Type Summary</h2>';
                echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                
                while ($row = mysqli_fetch_assoc($count_result)) {
                    $type = $row['question_type'];
                    $count = $row['count'];
                    
                    $bg_color = 'bg-blue-50 border-blue-200';
                    $text_color = 'text-blue-800';
                    
                    if ($type === 'short_answer') {
                        $bg_color = 'bg-red-50 border-red-200';
                        $text_color = 'text-red-800';
                    } elseif ($type === 'fill_in_blank') {
                        $bg_color = 'bg-green-50 border-green-200';
                        $text_color = 'text-green-800';
                    }
                    
                    echo '<div class="' . $bg_color . ' border rounded-lg p-4">';
                    echo '<h3 class="font-semibold ' . $text_color . '">' . ucwords(str_replace('_', ' ', $type)) . '</h3>';
                    echo '<p class="text-2xl font-bold ' . $text_color . '">' . $count . '</p>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
                
                // Check for short_answer questions
                $short_answer_query = "SELECT 
                    id, 
                    quiz_id, 
                    question_text, 
                    points, 
                    order_number, 
                    created_at
                FROM quiz_questions 
                WHERE question_type = 'short_answer'
                ORDER BY id";
                $short_answer_result = mysqli_query($conn, $short_answer_query);
                
                if (!$short_answer_result) {
                    throw new Exception("Error getting short_answer questions: " . mysqli_error($conn));
                }
                
                $short_answer_questions = mysqli_fetch_all($short_answer_result, MYSQLI_ASSOC);
                
                echo '<div class="mb-8">';
                echo '<h2 class="text-xl font-semibold text-gray-700 mb-4">Short Answer Questions (Need Update)</h2>';
                
                if (count($short_answer_questions) > 0) {
                    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
                    echo '<p class="text-red-800"><strong>Found ' . count($short_answer_questions) . ' questions that need to be updated from short_answer to fill_in_blank</strong></p>';
                    echo '</div>';
                    
                    echo '<div class="overflow-x-auto">';
                    echo '<table class="min-w-full bg-white border border-gray-200 rounded-lg">';
                    echo '<thead class="bg-gray-50">';
                    echo '<tr>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz ID</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question Preview</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody class="divide-y divide-gray-200">';
                    
                    foreach ($short_answer_questions as $question) {
                        echo '<tr class="hover:bg-gray-50">';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['id'] . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['quiz_id'] . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . htmlspecialchars(substr($question['question_text'], 0, 100)) . '...</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['points'] . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['order_number'] . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['created_at'] . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4">';
                    echo '<p class="text-green-800"><strong>✅ No short_answer questions found. All questions are up to date!</strong></p>';
                    echo '</div>';
                }
                echo '</div>';
                
                // Check for existing answers
                $answers_query = "SELECT 
                    qsa.question_id,
                    qq.quiz_id,
                    qq.question_text,
                    qsa.text_answer,
                    qsa.answered_at,
                    CONCAT(u.first_name, ' ', u.last_name) as student_name,
                    u.id as student_id
                FROM quiz_submission_answers qsa
                JOIN quiz_questions qq ON qsa.question_id = qq.id
                JOIN quiz_submissions qs ON qsa.submission_id = qs.id
                JOIN users u ON qs.student_id = u.id
                WHERE qq.question_type = 'short_answer' 
                    AND qsa.text_answer IS NOT NULL
                ORDER BY qsa.question_id, qsa.answered_at";
                $answers_result = mysqli_query($conn, $answers_query);
                
                if (!$answers_result) {
                    throw new Exception("Error getting existing answers: " . mysqli_error($conn));
                }
                
                $existing_answers = mysqli_fetch_all($answers_result, MYSQLI_ASSOC);
                
                echo '<div class="mb-8">';
                echo '<h2 class="text-xl font-semibold text-gray-700 mb-4">Existing Answers (Will Be Preserved)</h2>';
                
                if (count($existing_answers) > 0) {
                    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
                    echo '<p class="text-blue-800"><strong>Found ' . count($existing_answers) . ' existing answers for short_answer questions</strong></p>';
                    echo '</div>';
                    
                    echo '<div class="overflow-x-auto">';
                    echo '<table class="min-w-full bg-white border border-gray-200 rounded-lg">';
                    echo '<thead class="bg-gray-50">';
                    echo '<tr>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question ID</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Answer Preview</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Answered At</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody class="divide-y divide-gray-200">';
                    
                    foreach ($existing_answers as $answer) {
                        echo '<tr class="hover:bg-gray-50">';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $answer['question_id'] . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . htmlspecialchars($answer['student_name']) . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . htmlspecialchars(substr($answer['text_answer'], 0, 80)) . '...</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $answer['answered_at'] . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4">';
                    echo '<p class="text-gray-600">No existing answers found for short_answer questions.</p>';
                    echo '</div>';
                }
                echo '</div>';
                
                // Show fill_in_blank questions
                $fill_blank_query = "SELECT 
                    id, 
                    quiz_id, 
                    question_text, 
                    points, 
                    order_number, 
                    created_at,
                    updated_at
                FROM quiz_questions 
                WHERE question_type = 'fill_in_blank'
                ORDER BY updated_at DESC, id";
                $fill_blank_result = mysqli_query($conn, $fill_blank_query);
                
                if (!$fill_blank_result) {
                    throw new Exception("Error getting fill_in_blank questions: " . mysqli_error($conn));
                }
                
                $fill_blank_questions = mysqli_fetch_all($fill_blank_result, MYSQLI_ASSOC);
                
                echo '<div class="mb-8">';
                echo '<h2 class="text-xl font-semibold text-gray-700 mb-4">Fill in Blank Questions</h2>';
                
                if (count($fill_blank_questions) > 0) {
                    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
                    echo '<p class="text-green-800"><strong>Found ' . count($fill_blank_questions) . ' fill_in_blank questions</strong></p>';
                    echo '</div>';
                    
                    echo '<div class="overflow-x-auto">';
                    echo '<table class="min-w-full bg-white border border-gray-200 rounded-lg">';
                    echo '<thead class="bg-gray-50">';
                    echo '<tr>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz ID</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question Preview</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>';
                    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody class="divide-y divide-gray-200">';
                    
                    foreach ($fill_blank_questions as $question) {
                        $is_recent = strtotime($question['updated_at']) > strtotime('-1 hour');
                        $row_class = $is_recent ? 'bg-green-50' : 'hover:bg-gray-50';
                        
                        echo '<tr class="' . $row_class . '">';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['id'] . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['quiz_id'] . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . htmlspecialchars(substr($question['question_text'], 0, 100)) . '...</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['points'] . '</td>';
                        echo '<td class="px-4 py-2 text-sm text-gray-900">' . $question['updated_at'] . ($is_recent ? ' <span class="text-green-600">(Recent)</span>' : '') . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4">';
                    echo '<p class="text-gray-600">No fill_in_blank questions found.</p>';
                    echo '</div>';
                }
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="bg-red-50 border border-red-200 rounded-lg p-6">';
                echo '<h2 class="text-xl font-semibold text-red-800 mb-2">❌ Error!</h2>';
                echo '<p class="text-red-700">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
            
            <div class="mt-6 pt-6 border-t border-gray-200 flex space-x-4">
                <?php if (count($short_answer_questions) > 0): ?>
                <a href="update-short-answer-to-fill-in-blank.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Update Short Answer to Fill in Blank
                </a>
                <?php endif; ?>
                
                <a href="admin/dashboard.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Admin Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
