<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Get current semester
$semester_query = "SELECT id FROM semesters WHERE status = 'active' LIMIT 1";
$semester_result = mysqli_query($conn, $semester_query);
$semester_id = 1; // Default to first semester
if ($semester_result && $row = mysqli_fetch_assoc($semester_result)) {
    $semester_id = $row['id'];
}

// Get faculty members
$faculty_query = "SELECT id, first_name, last_name FROM faculty ORDER BY id";
$faculty_result = mysqli_query($conn, $faculty_query);
$faculty_members = [];
if ($faculty_result) {
    while ($row = mysqli_fetch_assoc($faculty_result)) {
        $faculty_members[] = $row;
    }
}

// Get students
$students_query = "SELECT id, first_name, last_name FROM students ORDER BY id LIMIT 20";
$students_result = mysqli_query($conn, $students_query);
$students = [];
if ($students_result) {
    while ($row = mysqli_fetch_assoc($students_result)) {
        $students[] = $row;
    }
}

// Sample evaluation data for different faculty performance levels
$evaluation_data = [
    // Faculty 1: High Performance (4.5+ average)
    [
        'faculty_id' => 1,
        'ratings' => [5, 5, 4, 5, 4, 5, 4, 5, 5, 4, 5, 4, 5, 5, 4],
        'feedback' => [
            "Excellent teacher! Very clear explanations.",
            "One of the best teachers I've had.",
            "Great teaching methods and very helpful.",
            "Very knowledgeable and patient.",
            "Amazing instructor, highly recommended!",
            "Clear communication and good examples.",
            "Very organized and professional.",
            "Excellent at explaining complex topics.",
            "Great feedback and support.",
            "Very engaging teaching style.",
            "Outstanding instructor!",
            "Clear and concise explanations.",
            "Very helpful and approachable.",
            "Excellent course structure.",
            "Great learning experience!"
        ]
    ],
    // Faculty 2: Medium Performance (3.5-4.4 average)
    [
        'faculty_id' => 3,
        'ratings' => [4, 3, 4, 3, 4, 4, 3, 4, 3, 4, 4, 3, 4, 3, 4],
        'feedback' => [
            "Good teacher, explains things well.",
            "Decent instructor, could be better.",
            "Generally helpful and knowledgeable.",
            "Okay teaching style.",
            "Good course content.",
            "Fair instructor, meets expectations.",
            "Reasonable teaching methods.",
            "Good explanations most of the time.",
            "Average teaching quality.",
            "Satisfactory instructor.",
            "Good course structure.",
            "Decent feedback provided.",
            "Generally helpful.",
            "Meets course requirements.",
            "Acceptable teaching quality."
        ]
    ],
    // Faculty 3: Low Performance (2.5-3.4 average)
    [
        'faculty_id' => 4,
        'ratings' => [3, 2, 3, 2, 3, 2, 3, 2, 3, 2, 3, 2, 3, 2, 3],
        'feedback' => [
            "Teacher needs improvement in explaining.",
            "Not very clear in instructions.",
            "Could be more organized.",
            "Teaching style needs work.",
            "Sometimes confusing explanations.",
            "Needs better communication.",
            "Course could be structured better.",
            "Limited feedback provided.",
            "Teaching methods could improve.",
            "Not very engaging.",
            "Could be more helpful.",
            "Needs better examples.",
            "Sometimes unclear.",
            "Could improve organization.",
            "Teaching quality needs work."
        ]
    ],
    // Faculty 4: Very High Performance (4.8+ average)
    [
        'faculty_id' => 5,
        'ratings' => [5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 5, 4, 5, 5],
        'feedback' => [
            "Absolutely outstanding teacher!",
            "Best instructor ever!",
            "Exceptional teaching skills!",
            "Very clear and excellent explanations.",
            "Perfect teaching methods!",
            "Amazing course structure!",
            "Outstanding communication!",
            "Excellent feedback and support.",
            "Incredible learning experience!",
            "Perfect instructor!",
            "Exceptional knowledge and skills!",
            "Outstanding course delivery!",
            "Very professional and helpful.",
            "Excellent teaching approach!",
            "Perfect learning environment!"
        ]
    ],
    // Faculty 5: Below Average Performance (2.0-2.4 average)
    [
        'faculty_id' => 6,
        'ratings' => [2, 2, 1, 2, 1, 2, 2, 1, 2, 2, 1, 2, 2, 1, 2],
        'feedback' => [
            "Teacher doesn't explain well.",
            "Very confusing instructions.",
            "Poor teaching methods.",
            "Not helpful at all.",
            "Difficult to understand.",
            "Poor course organization.",
            "No clear explanations.",
            "Very unhelpful instructor.",
            "Confusing teaching style.",
            "Poor communication skills.",
            "Not knowledgeable enough.",
            "Very disorganized.",
            "Poor feedback provided.",
            "Difficult to learn from.",
            "Not a good teacher."
        ]
    ]
];

$added_evaluations = 0;
$added_responses = 0;

echo "<h1>Adding Test Evaluation Data</h1>";
echo "<p>Adding dummy evaluation data for faculty clustering testing...</p>";

foreach ($evaluation_data as $faculty_data) {
    $faculty_id = $faculty_data['faculty_id'];
    $ratings = $faculty_data['ratings'];
    $feedback_texts = $faculty_data['feedback'];
    
    // Create evaluation sessions for this faculty
    for ($i = 0; $i < count($ratings); $i++) {
        // Select a random student
        $student = $students[array_rand($students)];
        
        // Create evaluation session
        $session_query = "INSERT INTO evaluation_sessions (evaluator_id, evaluator_type, evaluatee_id, evaluatee_type, main_category_id, semester_id, status, created_at, evaluation_date) VALUES (?, 'student', ?, 'teacher', 2, ?, 'completed', NOW(), CURDATE())";
        $session_stmt = mysqli_prepare($conn, $session_query);
        mysqli_stmt_bind_param($session_stmt, "iii", $student['id'], $faculty_id, $semester_id);
        
        if (mysqli_stmt_execute($session_stmt)) {
            $session_id = mysqli_insert_id($conn);
            $added_evaluations++;
            
            // Add evaluation response with rating and text feedback
            $response_query = "INSERT INTO evaluation_responses (evaluation_session_id, questionnaire_id, rating_value, text_response, created_at) VALUES (?, 1, ?, ?, NOW())";
            $response_stmt = mysqli_prepare($conn, $response_query);
            $feedback_text = $feedback_texts[$i];
            mysqli_stmt_bind_param($response_stmt, "iis", $session_id, $ratings[$i], $feedback_text);
            
            if (mysqli_stmt_execute($response_stmt)) {
                $added_responses++;
            }
        }
    }
}

echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h3>✅ Test Data Added Successfully!</h3>";
echo "<p><strong>Evaluation Sessions Added:</strong> $added_evaluations</p>";
echo "<p><strong>Evaluation Responses Added:</strong> $added_responses</p>";
echo "</div>";

// Show summary of faculty performance
echo "<h2>Faculty Performance Summary</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background: #f8f9fa;'>";
echo "<th style='padding: 10px;'>Faculty</th>";
echo "<th style='padding: 10px;'>Expected Avg Rating</th>";
echo "<th style='padding: 10px;'>Performance Level</th>";
echo "</tr>";

$performance_levels = [
    1 => "High Performance (4.5+)",
    3 => "Medium Performance (3.5-4.4)",
    4 => "Low Performance (2.5-3.4)",
    5 => "Very High Performance (4.8+)",
    6 => "Below Average (2.0-2.4)"
];

foreach ($evaluation_data as $data) {
    $faculty_id = $data['faculty_id'];
    $avg_rating = array_sum($data['ratings']) / count($data['ratings']);
    $level = $performance_levels[$faculty_id] ?? "Unknown";
    
    echo "<tr>";
    echo "<td style='padding: 10px;'>Faculty ID: $faculty_id</td>";
    echo "<td style='padding: 10px;'>" . round($avg_rating, 2) . "</td>";
    echo "<td style='padding: 10px;'>$level</td>";
    echo "</tr>";
}

echo "</table>";

echo "<div style='margin: 20px 0;'>";
echo "<a href='advanced_analytics.php?analysis_type=clustering' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Faculty Clustering</a>";
echo "&nbsp;&nbsp;";
echo "<a href='reports.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Back to Reports</a>";
echo "</div>";

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h3>📊 Test Data Details:</h3>";
echo "<ul>";
echo "<li><strong>5 Faculty Members</strong> with different performance levels</li>";
echo "<li><strong>15 evaluations each</strong> with realistic ratings and feedback</li>";
echo "<li><strong>Real students</strong> from the students table</li>";
echo "<li><strong>Current semester</strong> assignment</li>";
echo "<li><strong>Varied performance levels</strong> for meaningful clustering</li>";
echo "</ul>";
echo "</div>";
?>
