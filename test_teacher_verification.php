<?php
// Test script for teacher verification API
echo "Testing Teacher Verification API\n";
echo "================================\n\n";

// Test with a valid teacher ID (you may need to adjust this ID)
$testTeacherId = 1; // Change this to a valid teacher ID from your database

echo "Testing verification for Teacher ID: $testTeacherId\n";

// Make the API call
$url = "http://localhost/seait/api/teacher-availability-handler.php?action=verify_teacher&teacher_id=$testTeacherId";
$response = file_get_contents($url);

if ($response === false) {
    echo "❌ Error: Could not connect to API\n";
    echo "Make sure your web server is running and the URL is correct.\n";
} else {
    $data = json_decode($response, true);
    
    echo "Response:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    
    if ($data['success']) {
        echo "✅ Teacher verification successful!\n";
        echo "Teacher Name: " . $data['teacher']['name'] . "\n";
        echo "Department: " . $data['teacher']['department'] . "\n";
        echo "Position: " . $data['teacher']['position'] . "\n";
    } else {
        echo "❌ Teacher verification failed: " . $data['error'] . "\n";
        echo "\nPossible reasons:\n";
        echo "- Teacher ID $testTeacherId doesn't exist in the database\n";
        echo "- Teacher is not active (is_active = 0)\n";
        echo "- Database connection issues\n";
    }
}

echo "\nTo test with a different teacher ID, modify the \$testTeacherId variable in this script.\n";
?>
