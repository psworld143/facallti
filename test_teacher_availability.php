<?php
require_once 'config/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "<h1>Teacher Availability System Test</h1>";

// Test 1: Check if teacher_availability table exists
echo "<h2>Test 1: Database Table Check</h2>";
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'teacher_availability'");
if (mysqli_num_rows($table_check) > 0) {
    echo "✅ teacher_availability table exists<br>";
} else {
    echo "❌ teacher_availability table does not exist<br>";
}

// Test 2: Check if there are any faculty members
echo "<h2>Test 2: Faculty Members Check</h2>";
$faculty_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM faculty WHERE is_active = 1");
$faculty_count = mysqli_fetch_assoc($faculty_check)['count'];
echo "Found {$faculty_count} active faculty members<br>";

// Test 3: Check if there are any consultation hours
echo "<h2>Test 3: Consultation Hours Check</h2>";
$hours_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM consultation_hours WHERE is_active = 1");
$hours_count = mysqli_fetch_assoc($hours_check)['count'];
echo "Found {$hours_count} active consultation hours<br>";

// Test 4: Test marking a teacher as available
echo "<h2>Test 4: Mark Teacher Available</h2>";
if ($faculty_count > 0) {
    $faculty_result = mysqli_query($conn, "SELECT id, first_name, last_name FROM faculty WHERE is_active = 1 LIMIT 1");
    $faculty = mysqli_fetch_assoc($faculty_result);
    
    $teacher_id = $faculty['id'];
    $teacher_name = $faculty['first_name'] . ' ' . $faculty['last_name'];
    
    // Test the API endpoint
    $url = 'api/teacher-availability-handler.php?action=mark_available';
    $data = [
        'teacher_id' => $teacher_id,
        'notes' => 'Test availability from test script'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result !== false) {
        $response = json_decode($result, true);
        if ($response && $response['success']) {
            echo "✅ Successfully marked {$teacher_name} (ID: {$teacher_id}) as available<br>";
        } else {
            echo "❌ Failed to mark teacher as available: " . ($response['error'] ?? 'Unknown error') . "<br>";
        }
    } else {
        echo "❌ Failed to connect to API endpoint<br>";
    }
} else {
    echo "❌ No faculty members found to test with<br>";
}

// Test 5: Check available teachers
echo "<h2>Test 5: Check Available Teachers</h2>";
$available_check = mysqli_query($conn, "
    SELECT ta.*, f.first_name, f.last_name, f.department 
    FROM teacher_availability ta 
    JOIN faculty f ON ta.teacher_id = f.id 
    WHERE ta.availability_date = CURDATE() AND ta.status = 'available'
");
$available_count = mysqli_num_rows($available_check);
echo "Found {$available_count} teachers marked as available today<br>";

if ($available_count > 0) {
    echo "<h3>Available Teachers:</h3>";
    echo "<ul>";
    while ($row = mysqli_fetch_assoc($available_check)) {
        echo "<li>{$row['first_name']} {$row['last_name']} ({$row['department']}) - Scanned at: {$row['scan_time']}</li>";
    }
    echo "</ul>";
}

// Test 6: Test the view
echo "<h2>Test 6: Active Teachers View</h2>";
$view_check = mysqli_query($conn, "SELECT * FROM active_teachers_today");
$view_count = mysqli_num_rows($view_check);
echo "Active teachers view shows {$view_count} teachers<br>";

// Test 7: Show current date and time
echo "<h2>Test 7: Current Date/Time</h2>";
echo "Current date: " . date('Y-m-d') . "<br>";
echo "Current time: " . date('H:i:s') . "<br>";
echo "Current day: " . date('l') . "<br>";

echo "<h2>Test Complete</h2>";
echo "<p>If all tests pass, the teacher availability system is working correctly.</p>";
?>
