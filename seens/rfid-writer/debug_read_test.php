<?php
require_once 'arduino_communicator_simple.php';

echo "<h2>Debug READ Test</h2>";
echo "<p>Testing READ command with detailed output...</p>";

$port = '/dev/cu.usbserial-1130';

try {
    $arduino = new ArduinoCommunicatorSimple($port);
    
    if ($arduino->connect()) {
        echo "<p style='color: green;'>✓ Connected to Arduino</p>";
        
        // Test READ command
        echo "<h3>Testing READ command...</h3>";
        echo "<p><strong>Place an RFID card on the reader and click the button below:</strong></p>";
        echo "<button onclick='testRead()' style='padding: 10px 20px; background: blue; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test READ:4</button>";
        echo "<div id='readResult'></div>";
        
        $arduino->disconnect();
        echo "<p>✓ Disconnected</p>";
        
    } else {
        echo "<p style='color: red;'>✗ Failed to connect</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Debugging Steps:</h3>";
echo "<ol>";
echo "<li>Open Arduino IDE Serial Monitor (Tools > Serial Monitor, 9600 baud)</li>";
echo "<li>Place an RFID card on the reader</li>";
echo "<li>Click the Test READ button above</li>";
echo "<li>Watch the Serial Monitor for detailed output</li>";
echo "<li>Check what response comes back to PHP</li>";
echo "</ol>";

echo "<h3>Expected Serial Monitor Output:</h3>";
echo "<pre>";
echo "COMMAND_RECEIVED: READ:4\n";
echo "READ_REQUEST: Block 4\n";
echo "READ_PROCESS: Starting...\n";
echo "READ_PROCESS: Card detected\n";
echo "READ_PROCESS: Card type OK\n";
echo "READ_PROCESS: Authentication OK\n";
echo "READ_PROCESS: Read successful\n";
echo "READ_OK:4:data\n";
echo "</pre>";
?>

<script>
async function testRead() {
    const resultDiv = document.getElementById('readResult');
    resultDiv.innerHTML = '<p>Testing READ command...</p>';
    
    try {
        const response = await fetch('arduino_communicator_simple.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=read&block=4&port=<?= $port ?>'
        });
        
        const result = await response.json();
        
        resultDiv.innerHTML = `
            <div style='background: #f0f0f0; padding: 10px; border-radius: 5px; margin: 10px 0;'>
                <h4>Response Details:</h4>
                <p><strong>Status:</strong> ${result.status}</p>
                <p><strong>Message:</strong> ${result.message}</p>
                <p><strong>Data:</strong> ${result.data || 'No data'}</p>
                <p><strong>Block:</strong> ${result.block || 'No block info'}</p>
            </div>
        `;
        
        if (result.status === 'success') {
            resultDiv.innerHTML += '<p style="color: green;"><strong>✓ READ Success!</strong></p>';
        } else {
            resultDiv.innerHTML += '<p style="color: red;"><strong>✗ READ Failed</strong></p>';
        }
        
    } catch (error) {
        resultDiv.innerHTML = `<p style='color: red;'><strong>✗ Error:</strong> ${error.message}</p>`;
    }
}
</script>
