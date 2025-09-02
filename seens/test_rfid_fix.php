<?php
/**
 * Test RFID Fix
 * Test the fixed RFID writing functionality
 */

// Include configuration
include('configuration.php');

echo "<h2>Testing RFID Writing Fix</h2>";

// Test the write_rfid.php script directly
echo "<h3>Testing write_rfid.php</h3>";

// Set up test data
$_POST['token'] = 'Seait123';
$_POST['action'] = 'write_rfid';
$_POST['student_id'] = '2017-00202'; // Use the student ID from the error

// Capture output
ob_start();
include 'backend_scripts/write_rfid.php';
$response = ob_get_clean();

echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";

// Test if the RFID API file can be included
echo "<h3>Testing RFID API File Inclusion</h3>";

$rfidApiPath = __DIR__ . '/rfid-writer/api.php';
echo "RFID API Path: $rfidApiPath<br>";

if (file_exists($rfidApiPath)) {
    echo "✅ RFID API file exists<br>";
    
    // Test if we can include it
    try {
        // Set up test data for the API
        $_POST['action'] = 'write_student_id';
        $_POST['student_id'] = '2017-00202';
        $_POST['api_key'] = 'seens_rfid_2024';
        
        ob_start();
        include $rfidApiPath;
        $apiResponse = ob_get_clean();
        
        echo "✅ RFID API included successfully<br>";
        echo "API Response: <pre>" . htmlspecialchars($apiResponse) . "</pre>";
        
    } catch (Exception $e) {
        echo "❌ Error including RFID API: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ RFID API file not found<br>";
}

echo "<h3>Test Complete</h3>";
?>
