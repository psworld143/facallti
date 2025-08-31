<?php
session_start();
require_once '../config/database.php';

echo "<h1>Minimal Faculty Test</h1>";

// Check session
echo "<h2>Session:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Check database connection
echo "<h2>Database Connection:</h2>";
if ($conn) {
    echo "✅ Database connected<br>";
    
    // Test simple query
    $test_query = "SELECT 1 as test";
    $result = mysqli_query($conn, $test_query);
    if ($result) {
        echo "✅ Simple query works<br>";
    } else {
        echo "❌ Simple query failed: " . mysqli_error($conn) . "<br>";
    }
    
    // Test faculty query with parameter
    if (isset($_SESSION['user_id'])) {
        $faculty_id = $_SESSION['user_id'];
        echo "<h2>Testing Faculty Query:</h2>";
        echo "Faculty ID: " . $faculty_id . "<br>";
        
        $query = "SELECT id, first_name, last_name FROM faculty WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt) {
            echo "✅ Statement prepared<br>";
            mysqli_stmt_bind_param($stmt, 'i', $faculty_id);
            $execute_result = mysqli_stmt_execute($stmt);
            
            if ($execute_result) {
                echo "✅ Statement executed<br>";
                $result = mysqli_stmt_get_result($stmt);
                $faculty = mysqli_fetch_assoc($result);
                if ($faculty) {
                    echo "✅ Faculty found: " . $faculty['first_name'] . " " . $faculty['last_name'] . "<br>";
                } else {
                    echo "❌ Faculty not found<br>";
                }
            } else {
                echo "❌ Statement execution failed: " . mysqli_stmt_error($stmt) . "<br>";
            }
        } else {
            echo "❌ Statement preparation failed: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "❌ No user_id in session<br>";
    }
} else {
    echo "❌ Database connection failed<br>";
}
?>
