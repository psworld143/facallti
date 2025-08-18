<?php
// Test file to verify reports functionality
session_start();

// Simulate a logged-in guidance officer
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'guidance_officer';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'User';

// Test database connection
require_once '../config/database.php';

if ($conn) {
    echo "✅ Database connection successful<br>";
} else {
    echo "❌ Database connection failed<br>";
}

// Test basic queries
$test_queries = [
    "SELECT COUNT(*) as total FROM evaluation_sessions" => "Evaluation Sessions",
    "SELECT COUNT(*) as total FROM users WHERE role = 'teacher'" => "Teachers",
    "SELECT COUNT(*) as total FROM students" => "Students",
    "SELECT COUNT(*) as total FROM semesters WHERE status = 'active'" => "Active Semesters"
];

foreach ($test_queries as $query => $description) {
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "✅ $description: " . $row['total'] . "<br>";
    } else {
        echo "❌ $description query failed: " . mysqli_error($conn) . "<br>";
    }
}

// Test clustering analysis file
if (file_exists('clustering_analysis.php')) {
    echo "✅ Clustering analysis file exists<br>";
} else {
    echo "❌ Clustering analysis file not found<br>";
}

// Test report include files
$report_files = [
    'reports/overview_report.php',
    'reports/clustering_report.php',
    'reports/performance_report.php',
    'reports/training_report.php',
    'reports/detailed_report.php'
];

foreach ($report_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file not found<br>";
    }
}

echo "<br><strong>Reports page should now be working properly!</strong><br>";
echo "<a href='reports.php'>Go to Reports Page</a>";
?>