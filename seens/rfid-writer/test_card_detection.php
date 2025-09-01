<?php
require_once 'arduino_communicator.php';

echo "<h2>Card Detection Test</h2>";
echo "<p>This test will check if your Arduino can detect RFID cards at all.</p>";

$port = '/dev/cu.usbserial-1130';

try {
    $arduino = new ArduinoCommunicator($port);
    
    if ($arduino->connect()) {
        echo "<p style='color: green;'>✓ Connected to Arduino</p>";
        
        // Send a simple command to trigger card detection
        echo "<h3>Testing Card Detection</h3>";
        echo "<p>Place an RFID card on the reader and watch for detection messages...</p>";
        
        // Send a status command to see if Arduino responds
        $status = $arduino->getStatus();
        echo "<p>Arduino Status: " . ($status ? $status : 'No response') . "</p>";
        
        // Try to read from block 4 with a card
        echo "<p><strong>Now place a card on the reader and click the button below:</strong></p>";
        echo "<button onclick='testRead()' style='padding: 10px 20px; background: blue; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test Read with Card</button>";
        echo "<div id='result'></div>";
        
        $arduino->disconnect();
        
    } else {
        echo "<p style='color: red;'>✗ Failed to connect to Arduino</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Troubleshooting Steps:</h3>";
echo "<ol>";
echo "<li><strong>Check Hardware:</strong> Make sure RC522 module is properly connected to Arduino</li>";
echo "<li><strong>Check Power:</strong> Ensure Arduino and RC522 are powered (3.3V)</li>";
echo "<li><strong>Check Connections:</strong> Verify SDA→Pin 10, SCK→Pin 13, MOSI→Pin 11, MISO→Pin 12, RST→Pin 9</li>";
echo "<li><strong>Check Card Type:</strong> Use a MiFare Classic card (most common RFID cards)</li>";
echo "<li><strong>Check Card Position:</strong> Place card directly on the RC522 antenna area</li>";
echo "<li><strong>Check Arduino Sketch:</strong> Make sure arduino_rfid_writer.ino is uploaded</li>";
echo "</ol>";

echo "<h3>Common Issues:</h3>";
echo "<ul>";
echo "<li><strong>Wrong card type:</strong> Some cards are not MiFare Classic</li>";
echo "<li><strong>Card too far:</strong> Card must be very close to the reader</li>";
echo "<li><strong>Power issues:</strong> RC522 needs stable 3.3V power</li>";
echo "<li><strong>Connection issues:</strong> Loose wires or wrong pins</li>";
echo "<li><strong>Card damaged:</strong> Try a different card</li>";
echo "</ul>";
?>

<script>
async function testRead() {
    const resultDiv = document.getElementById('result');
    resultDiv.innerHTML = '<p>Testing read operation...</p>';
    
    try {
        const response = await fetch('arduino_communicator.php', {
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
            `;
        } else {
            resultDiv.innerHTML = `
                <p style='color: red;'><strong>✗ Failed:</strong> ${result.message}</p>
                <p>This means the card was not detected or there's a hardware issue.</p>
            `;
        }
        
    } catch (error) {
        resultDiv.innerHTML = `<p style='color: red;'><strong>✗ Error:</strong> ${error.message}</p>`;
    }
}
</script>
