<?php
/**
 * Test RFID Functionality
 * Simple test script to verify RFID writing works
 */

// Include configuration
include('configuration.php');

echo "<h2>RFID Functionality Test</h2>";

// Test database connection
try {
    $conn = establishConnection();
    if ($conn) {
        echo "✅ Database connection successful<br>";
        
        // Test if activity_logs table exists
        $result = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($result->num_rows > 0) {
            echo "✅ activity_logs table exists<br>";
        } else {
            echo "❌ activity_logs table does not exist<br>";
        }
        
        // Test if seens_student table exists and has data
        $result = $conn->query("SELECT COUNT(*) as count FROM seens_student");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ seens_student table exists with {$row['count']} records<br>";
            
            // Show a sample student
            if ($row['count'] > 0) {
                $sample = $conn->query("SELECT ss_id_no FROM seens_student LIMIT 1");
                $student = $sample->fetch_assoc();
                echo "📋 Sample student ID: {$student['ss_id_no']}<br>";
            }
        } else {
            echo "❌ seens_student table error<br>";
        }
        
    } else {
        echo "❌ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test RFID API endpoint
echo "<h3>Testing RFID API Endpoint</h3>";

$testUrl = 'http://localhost/seens/backend_scripts/write_rfid.php';
$postData = [
    'token' => 'Seait123',
    'action' => 'write_rfid',
    'student_id' => 'TEST123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ cURL Error: $curlError<br>";
} else {
    echo "✅ API endpoint accessible (HTTP $httpCode)<br>";
    if ($response) {
        $jsonResponse = json_decode($response, true);
        if ($jsonResponse) {
            echo "✅ JSON response received<br>";
            echo "Response: " . json_encode($jsonResponse, JSON_PRETTY_PRINT) . "<br>";
        } else {
            echo "❌ Invalid JSON response<br>";
            echo "Raw response: $response<br>";
        }
    }
}

echo "<h3>Test Complete</h3>";
?>
