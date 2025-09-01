<?php
require_once 'arduino_communicator.php';

echo "<h2>Debug Card Reading Test</h2>";

$port = '/dev/cu.usbserial-1130';

echo "<h3>Testing with port: {$port}</h3>";

try {
    $arduino = new ArduinoCommunicator($port);
    echo "<p>✓ ArduinoCommunicator created</p>";
    
    if ($arduino->connect()) {
        echo "<p style='color: green;'>✓ Connected to Arduino</p>";
        
        // Test status
        $status = $arduino->getStatus();
        echo "<p>Status: " . ($status ? $status : 'No response') . "</p>";
        
        // Test communication
        $testResult = $arduino->testCommunication();
        echo "<p>Communication Test: " . ($testResult ? 'PASSED' : 'FAILED') . "</p>";
        
        // Try to read from block 4
        echo "<h3>Testing Card Read from Block 4</h3>";
        echo "<p>Place an RFID card on the reader and press Enter...</p>";
        
        // Wait for user input (simulate)
        echo "<p>Attempting to read from block 4...</p>";
        
        $result = $arduino->readFromCard(4);
        
        if ($result !== false) {
            echo "<p style='color: green;'>✓ Read successful!</p>";
            echo "<p><strong>Data:</strong> " . htmlspecialchars($result) . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Read failed</p>";
        }
        
        $arduino->disconnect();
        echo "<p>✓ Disconnected</p>";
        
    } else {
        echo "<p style='color: red;'>✗ Failed to connect</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
}

echo "<h3>Possible Issues:</h3>";
echo "<ul>";
echo "<li>Arduino sketch not uploaded or wrong sketch</li>";
echo "<li>RFID module not properly connected</li>";
echo "<li>Card not placed correctly on reader</li>";
echo "<li>Card type not supported (needs MiFare Classic)</li>";
echo "<li>Card is empty or has different authentication</li>";
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<p>1. Make sure arduino_rfid_writer.ino is uploaded to Arduino</p>";
echo "<p>2. Check RFID module connections</p>";
echo "<p>3. Try with a different MiFare Classic card</p>";
echo "<p>4. Check Arduino Serial Monitor for any error messages</p>";
?>
