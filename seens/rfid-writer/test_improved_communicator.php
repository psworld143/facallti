<?php
require_once 'arduino_communicator_improved.php';

echo "<h2>Improved Arduino Communicator Test</h2>";

$port = '/dev/cu.usbserial-1130';

try {
    $arduino = new ArduinoCommunicatorImproved($port);
    echo "<p>✓ ArduinoCommunicatorImproved created</p>";
    
    if ($arduino->connect()) {
        echo "<p style='color: green;'>✓ Connected to Arduino</p>";
        
        // Test status
        $status = $arduino->getStatus();
        echo "<p>Status: " . ($status ? $status : 'No response') . "</p>";
        
        // Test communication
        $testResult = $arduino->testCommunication();
        echo "<p>Communication Test: " . ($testResult ? 'PASSED' : 'FAILED') . "</p>";
        
        echo "<h3>Card Reading Test</h3>";
        echo "<p><strong>Instructions:</strong></p>";
        echo "<ol>";
        echo "<li>Place an RFID card on the reader</li>";
        echo "<li>Click the button below to test reading</li>";
        echo "<li>Keep the card on the reader during the test</li>";
        echo "</ol>";
        
        echo "<button onclick='testRead()' style='padding: 10px 20px; background: blue; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 10px 0;'>Test Read from Block 4</button>";
        echo "<div id='result'></div>";
        
        $arduino->disconnect();
        echo "<p>✓ Disconnected</p>";
        
    } else {
        echo "<p style='color: red;'>✗ Failed to connect</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Improvements in this version:</h3>";
echo "<ul>";
echo "<li><strong>Better timing:</strong> Longer timeouts and better delays</li>";
echo "<li><strong>Buffer clearing:</strong> Clears pending data before operations</li>";
echo "<li><strong>Improved error handling:</strong> More detailed error messages</li>";
echo "<li><strong>Better response parsing:</strong> More reliable response detection</li>";
echo "</ul>";
?>

<script>
async function testRead() {
    const resultDiv = document.getElementById('result');
    resultDiv.innerHTML = '<p>Testing read operation with improved communicator...</p>';
    
    try {
        const response = await fetch('arduino_communicator_improved.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=read&block=4&port=<?= $port ?>'
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            resultDiv.innerHTML = `
                <p style='color: green;'><strong>✓ Success!</strong></p>
                <p><strong>Data:</strong> ${result.data}</p>
                <p><strong>Block:</strong> ${result.block}</p>
                <p><strong>Message:</strong> ${result.message}</p>
            `;
        } else {
            resultDiv.innerHTML = `
                <p style='color: red;'><strong>✗ Failed:</strong> ${result.message}</p>
                <p><strong>Troubleshooting:</strong></p>
                <ul>
                    <li>Make sure card is placed directly on the reader</li>
                    <li>Try moving the card around the reader area</li>
                    <li>Check if the card is a MiFare Classic type</li>
                    <li>Try a different card if available</li>
                </ul>
            `;
        }
        
    } catch (error) {
        resultDiv.innerHTML = `<p style='color: red;'><strong>✗ Error:</strong> ${error.message}</p>`;
    }
}
</script>
