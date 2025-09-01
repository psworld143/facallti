<?php
require_once 'arduino_communicator_simple.php';

echo "<h2>UID Reading Test</h2>";
echo "<p>Testing UID reading functionality...</p>";

$port = '/dev/cu.usbserial-1130';

try {
    $arduino = new ArduinoCommunicatorSimple($port);
    
    if ($arduino->connect()) {
        echo "<p style='color: green;'>✓ Connected to Arduino</p>";
        
        // Test UID reading
        echo "<h3>Testing UID Reading...</h3>";
        echo "<p><strong>Place an RFID card on the reader and click the button below:</strong></p>";
        echo "<button onclick='testUID()' style='padding: 10px 20px; background: purple; color: white; border: none; border-radius: 5px; cursor: pointer;'>Read UID</button>";
        echo "<div id='uidResult'></div>";
        
        // Test basic READ command
        echo "<h3>Testing Basic READ Command...</h3>";
        echo "<p><strong>Place an RFID card on the reader and click the button below:</strong></p>";
        echo "<button onclick='testRead()' style='padding: 10px 20px; background: blue; color: white; border: none; border-radius: 5px; cursor: pointer;'>Read Block 4</button>";
        echo "<div id='readResult'></div>";
        
        $arduino->disconnect();
        echo "<p>✓ Disconnected</p>";
        
    } else {
        echo "<p style='color: red;'>✗ Failed to connect</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Instructions:</h3>";
echo "<ol>";
echo "<li>Upload <strong>arduino_rfid_simple.ino</strong> to your Arduino</li>";
echo "<li>Open Arduino Serial Monitor (9600 baud)</li>";
echo "<li>Place an RFID card on the reader</li>";
echo "<li>Click the buttons above to test</li>";
echo "<li>Watch the Serial Monitor for detailed output</li>";
echo "</ol>";

echo "<h3>Expected UID Output:</h3>";
echo "<pre>";
echo "CMD: READ_UID\n";
echo "READING_UID...\n";
echo "UID: 04 A3 B2 C1 D0 E9 F8\n";
echo "TYPE: MIFARE 1KB\n";
echo "UID_READY\n";
echo "</pre>";
?>

<script>
async function testUID() {
    const resultDiv = document.getElementById('uidResult');
    resultDiv.innerHTML = '<p>Testing UID reading...</p>';
    
    try {
        // Send READ_UID command directly
        const response = await fetch('arduino_communicator_simple.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=uid&port=<?= $port ?>'
        });
        
        const result = await response.json();
        
        resultDiv.innerHTML = `
            <div style='background: #f0f0f0; padding: 10px; border-radius: 5px; margin: 10px 0;'>
                <h4>UID Response:</h4>
                <p><strong>Status:</strong> ${result.status}</p>
                <p><strong>Message:</strong> ${result.message}</p>
                <p><strong>UID:</strong> ${result.uid || 'No UID'}</p>
            </div>
        `;
        
    } catch (error) {
        resultDiv.innerHTML = `<p style='color: red;'><strong>✗ Error:</strong> ${error.message}</p>`;
    }
}

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
                <h4>READ Response:</h4>
                <p><strong>Status:</strong> ${result.status}</p>
                <p><strong>Message:</strong> ${result.message}</p>
                <p><strong>Data:</strong> ${result.data || 'No data'}</p>
                <p><strong>Block:</strong> ${result.block || 'No block info'}</p>
            </div>
        `;
        
    } catch (error) {
        resultDiv.innerHTML = `<p style='color: red;'><strong>✗ Error:</strong> ${error.message}</p>`;
    }
}
</script>
