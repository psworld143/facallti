<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Test database connection
$connection_status = "Database Connection Test\n";
$connection_status .= "========================\n";

if ($conn) {
    $connection_status .= "✓ Database connection successful\n";
    $connection_status .= "Host: $host\n";
    $connection_status .= "Database: $dbname\n";
    $connection_status .= "Username: $username\n";
    
    // Test faculty table
    $faculty_query = "SELECT COUNT(*) as count FROM faculty";
    $faculty_result = mysqli_query($conn, $faculty_query);
    if ($faculty_result) {
        $faculty_count = mysqli_fetch_assoc($faculty_result)['count'];
        $connection_status .= "✓ Faculty table accessible - $faculty_count records\n";
    } else {
        $connection_status .= "✗ Faculty table error: " . mysqli_error($conn) . "\n";
    }
    
    // Test colleges table
    $colleges_query = "SELECT COUNT(*) as count FROM colleges WHERE is_active = 1";
    $colleges_result = mysqli_query($conn, $colleges_query);
    if ($colleges_result) {
        $colleges_count = mysqli_fetch_assoc($colleges_result)['count'];
        $connection_status .= "✓ Colleges table accessible - $colleges_count active records\n";
    } else {
        $connection_status .= "✗ Colleges table error: " . mysqli_error($conn) . "\n";
    }
    
    // Test insert operation
    $test_query = "INSERT INTO faculty (first_name, last_name, email, position, department, password, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $test_stmt = mysqli_prepare($conn, $test_query);
    if ($test_stmt) {
        $test_first_name = "Test";
        $test_last_name = "User";
        $test_email = "test" . time() . "@test.com";
        $test_position = "Test Position";
        $test_department = "Test Department";
        $test_password = password_hash("test123", PASSWORD_DEFAULT);
        $test_active = 1;
        
        mysqli_stmt_bind_param($test_stmt, "ssssssi", $test_first_name, $test_last_name, $test_email, $test_position, $test_department, $test_password, $test_active);
        
        if (mysqli_stmt_execute($test_stmt)) {
            $test_id = mysqli_insert_id($conn);
            $connection_status .= "✓ Insert test successful - ID: $test_id\n";
            
            // Clean up test record
            $delete_query = "DELETE FROM faculty WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, "i", $test_id);
            mysqli_stmt_execute($delete_stmt);
            $connection_status .= "✓ Test record cleaned up\n";
        } else {
            $connection_status .= "✗ Insert test failed: " . mysqli_stmt_error($test_stmt) . "\n";
        }
    } else {
        $connection_status .= "✗ Prepare statement failed: " . mysqli_error($conn) . "\n";
    }
    
} else {
    $connection_status .= "✗ Database connection failed: " . mysqli_connect_error() . "\n";
}

// Test file upload directory
$connection_status .= "\nFile Upload Test\n";
$connection_status .= "================\n";

$uploads_dir = '../uploads/faculty/';
if (is_dir($uploads_dir)) {
    $connection_status .= "✓ Uploads directory exists\n";
    if (is_writable($uploads_dir)) {
        $connection_status .= "✓ Uploads directory is writable\n";
        
        // Test file creation
        $test_file = $uploads_dir . 'test_' . time() . '.txt';
        if (file_put_contents($test_file, 'test') !== false) {
            $connection_status .= "✓ File creation test successful\n";
            unlink($test_file);
            $connection_status .= "✓ Test file cleaned up\n";
        } else {
            $connection_status .= "✗ File creation test failed\n";
        }
    } else {
        $connection_status .= "✗ Uploads directory is not writable\n";
    }
} else {
    $connection_status .= "✗ Uploads directory does not exist\n";
}

// Test session
$connection_status .= "\nSession Test\n";
$connection_status .= "============\n";

if (isset($_SESSION['user_id'])) {
    $connection_status .= "✓ Session active - User ID: " . $_SESSION['user_id'] . "\n";
    $connection_status .= "✓ User role: " . $_SESSION['role'] . "\n";
} else {
    $connection_status .= "✗ Session not active\n";
}

// Test PHP configuration
$connection_status .= "\nPHP Configuration\n";
$connection_status .= "==================\n";

$connection_status .= "PHP Version: " . phpversion() . "\n";
$connection_status .= "Max Upload Size: " . ini_get('upload_max_filesize') . "\n";
$connection_status .= "Max Post Size: " . ini_get('post_max_size') . "\n";
$connection_status .= "Memory Limit: " . ini_get('memory_limit') . "\n";
$connection_status .= "Max Execution Time: " . ini_get('max_execution_time') . "\n";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Database Connection Test</h1>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Test Results</h2>
            <pre class="bg-gray-100 p-4 rounded-lg text-sm font-mono overflow-x-auto"><?php echo htmlspecialchars($connection_status); ?></pre>
        </div>

        <div class="mt-8">
            <a href="faculty.php" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                Back to Faculty Management
            </a>
            <a href="debug_faculty.php" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 ml-4">
                Faculty Debug Page
            </a>
        </div>
    </div>
</body>
</html>
