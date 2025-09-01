<?php
require_once 'arduino_communicator_simple.php';

echo "<h2>Arduino Command Test</h2>";
echo "<p>Testing individual commands to see what the Arduino responds with.</p>";

$port = '/dev/cu.usbserial-1130';

try {
    $arduino = new ArduinoCommunicatorSimple($port);
    
    if ($arduino->connect()) {
        echo "<p style='color: green;'>✓ Connected to Arduino</p>";
        
        echo "<h3>Testing Commands:</h3>";
        
        // Test 1: STATUS command
        echo "<h4>1. Testing STATUS command</h4>";
        $status = $arduino->getStatus();
        echo "<p>Response: " . ($status ? $status : 'No response') . "</p>";
        
        // Test 2: PING command
        echo "<h4>2. Testing PING command</h4>";
        $pingResult = $arduino->testCommunication();
        echo "<p>Response: " . ($pingResult ? 'PONG received' : 'No PONG') . "</p>";
        
        // Test 3: READ command with card
        echo "<h4>3. Testing READ command</h4>";
        echo "<p><strong>Place an RFID card on the reader and click the button below:</strong></p>";
        echo "<button onclick='testRead()' style='padding: 10px 20px; background: blue; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test READ:4</button>";
        echo "<div id='readResult'></div>";
        
        // Test 4: WRITE command
        echo "<h4>4. Testing WRITE command</h4>";
        echo "<p><strong>Place an RFID card on the reader and click the button below:</strong></p>";
        echo "<button onclick='testWrite()' style='padding: 10px 20px; background: green; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test WRITE:4:TEST123</button>";
        echo "<div id='writeResult'></div>";
        
        $arduino->disconnect();
        echo "<p>✓ Disconnected</p>";
        
    } else {
        echo "<p style='color: red;'>✗ Failed to connect</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Expected Responses:</h3>";
echo "<ul>";
echo "<li><strong>STATUS:</strong> Should return 'RFID_WRITER_READY'</li>";
echo "<li><strong>PING:</strong> Should return 'PONG'</li>";
echo "<li><strong>READ:</strong> Should return 'READ_OK:4:data' or 'READ_ERROR'</li>";
echo "<li><strong>WRITE:</strong> Should return 'WRITE_OK' or 'WRITE_ERROR'</li>";
echo "</ul>";

echo "<h3>If commands are not working:</h3>";
echo "<ol>";
echo "<li>Check if Arduino Serial Monitor is closed</li>";
echo "<li>Make sure the correct sketch is uploaded</li>";
echo "<li>Check if the Arduino is receiving commands (watch Serial Monitor)</li>";
echo "<li>Try uploading the debug sketch (arduino_rfid_writer_simple.ino)</li>";
echo "</ol>";
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
        
        if (result.status === 'success') {
            resultDiv.innerHTML = `
                <p style='color: green;'><strong>✓ READ Success!</strong></p>
                <p><strong>Data:</strong> ${result.data}</p>
                <p><strong>Message:</strong> ${result.message}</p>
            `;
        } else {
            resultDiv.innerHTML = `
                <p style='color: red;'><strong>✗ READ Failed:</strong> ${result.message}</p>
            `;
        }
        
    } catch (error) {
        resultDiv.innerHTML = `<p style='color: red;'><strong>✗ Error:</strong> ${error.message}</p>`;
    }
}

async function testWrite() {
    const resultDiv = document.getElementById('writeResult');
    resultDiv.innerHTML = '<p>Testing WRITE command...</p>';
    
    try {
        const response = await fetch('arduino_communicator_simple.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=write&data=TEST123&block=4&port=<?= $port ?>'
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            resultDiv.innerHTML = `
                <p style='color: green;'><strong>✓ WRITE Success!</strong></p>
                <p><strong>Message:</strong> ${result.message}</p>
            `;
        } else {
            resultDiv.innerHTML = `
                <p style='color: red;'><strong>✗ WRITE Failed:</strong> ${result.message}</p>
            `;
        }
        
    } catch (error) {
        resultDiv.innerHTML = `<p style='color: red;'><strong>✗ Error:</strong> ${error.message}</p>`;
    }
}
</script>
