<?php
/**
 * Simple test for RFID Writer web interface
 */

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'status';
$_POST['port'] = (PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0';

// Capture output
ob_start();

// Include the RFID writer
require_once 'rfid_writer.php';

// Get the output
$output = ob_get_clean();

echo "<h2>RFID Writer Web Test</h2>";
echo "<h3>Request:</h3>";
echo "Action: " . $_POST['action'] . "<br>";
echo "Port: " . $_POST['port'] . "<br>";

echo "<h3>Response:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Try to parse JSON
$json = json_decode($output, true);
if ($json) {
    echo "<h3>Parsed JSON:</h3>";
    echo "<pre>" . print_r($json, true) . "</pre>";
} else {
    echo "<h3>JSON Parse Error:</h3>";
    echo "Could not parse JSON response<br>";
    echo "JSON Error: " . json_last_error_msg() . "<br>";
}
?>
