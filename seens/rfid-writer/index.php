<?php
require_once 'rfid_writer.php';

// Get available ports
$availablePorts = RFIDWriter::getAvailablePorts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEENS RFID Writer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-credit-card text-blue-600"></i>
                SEENS RFID Writer
            </h1>
            <p class="text-gray-600">Write and read data from MiFare RFID cards</p>
        </div>

        <!-- Connection Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-plug text-green-600"></i>
                Connection Status
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Serial Port</label>
                    <select id="portSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php 
                        $defaultPort = (PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0';
                        foreach ($availablePorts as $port): 
                            $selected = ($port === $defaultPort) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($port) ?>" <?= $selected ?>><?= htmlspecialchars($port) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php if (PHP_OS === 'WINNT'): ?>
                            Windows: Usually COM3, COM4, or COM5 for Arduino
                        <?php else: ?>
                            Linux/Mac: Usually /dev/ttyUSB0 or /dev/ttyACM0
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-end space-x-2">
                    <button id="connectBtn" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-link mr-2"></i>Connect
                    </button>
                    <a href="test_mode.php" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm">
                        <i class="fas fa-flask mr-1"></i>Test Mode
                    </a>
                    <span id="connectionStatus" class="ml-4 text-sm text-gray-600">Not connected</span>
                </div>
            </div>
            <!-- Connection Error Display -->
            <div id="connectionError" class="mt-4 hidden"></div>
        </div>

        <!-- Main Interface -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Write Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-edit text-blue-600"></i>
                    Write to RFID Card
                </h2>
                <form id="writeForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Student ID Number</label>
                        <input type="text" id="writeData" name="data" placeholder="e.g., 2021-0001" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               maxlength="16">
                        <p class="text-xs text-gray-500 mt-1">Maximum 16 characters</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Block Number</label>
                        <input type="number" id="writeBlock" name="block" value="4" min="0" max="63"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Block 0-63 (Block 4 is commonly used for data)</p>
                    </div>
                    <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <i class="fas fa-save mr-2"></i>Write to Card
                    </button>
                </form>
                <div id="writeResult" class="mt-4 hidden"></div>
            </div>

            <!-- Read Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-eye text-green-600"></i>
                    Read from RFID Card
                </h2>
                <form id="readForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Block Number</label>
                        <input type="number" id="readBlock" name="block" value="4" min="0" max="63"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Block 0-63 (Block 4 is commonly used for data)</p>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-search mr-2"></i>Read from Card
                    </button>
                </form>
                <div id="readResult" class="mt-4 hidden"></div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-yellow-600"></i>
                Instructions
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Hardware Setup:</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Connect RC522 RFID module to Arduino Uno</li>
                        <li>• SDA → Pin 10, SCK → Pin 13</li>
                        <li>• MOSI → Pin 11, MISO → Pin 12</li>
                        <li>• RST → Pin 9, 3.3V → 3.3V, GND → GND</li>
                        <li>• Upload arduino_rfid_writer.ino to Arduino</li>
                        <?php if (PHP_OS === 'WINNT'): ?>
                        <li>• Install Arduino drivers if not already installed</li>
                        <li>• Check Device Manager for COM port assignment</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Usage:</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Select the correct serial port</li>
                        <?php if (PHP_OS === 'WINNT'): ?>
                        <li>• Windows: Check Device Manager for COM port (usually COM3-COM8)</li>
                        <?php else: ?>
                        <li>• Linux/Mac: Usually /dev/ttyUSB0 or /dev/ttyACM0</li>
                        <?php endif; ?>
                        <li>• Click "Connect" to establish connection</li>
                        <li>• Place MiFare card on the reader</li>
                        <li>• Enter student ID and click "Write to Card"</li>
                        <li>• Use "Read from Card" to verify data</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isConnected = false;

        // Connect button
        document.getElementById('connectBtn').addEventListener('click', async function() {
            const port = document.getElementById('portSelect').value;
            const statusSpan = document.getElementById('connectionStatus');
            const errorDiv = document.getElementById('connectionError');
            
            // Clear previous errors
            errorDiv.classList.add('hidden');
            
            // Show connecting status
            statusSpan.textContent = 'Connecting...';
            statusSpan.className = 'ml-4 text-sm text-yellow-600';
            
            try {
                const response = await fetch('rfid_writer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=status&port=${encodeURIComponent(port)}`
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                let result;
                
                try {
                    result = JSON.parse(text);
                } catch (jsonError) {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                }
                
                if (result.status === 'success') {
                    isConnected = true;
                    statusSpan.textContent = 'Connected';
                    statusSpan.className = 'ml-4 text-sm text-green-600';
                    this.textContent = 'Disconnect';
                    this.className = 'bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500';
                } else {
                    throw new Error(result.message || 'Unknown error');
                }
            } catch (error) {
                statusSpan.textContent = 'Connection failed';
                statusSpan.className = 'ml-4 text-sm text-red-600';
                console.error('Connection error:', error);
                
                // Display error in UI instead of alert
                errorDiv.className = 'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                errorDiv.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Connection Error:</strong> ${error.message}
                    </div>
                    <div class="mt-2 text-sm">
                        <p><strong>Troubleshooting:</strong></p>
                        <ul class="list-disc list-inside mt-1">
                            <li>Make sure Arduino is connected and powered</li>
                            <li>Check if the correct port is selected</li>
                            <li>Verify Arduino sketch is uploaded</li>
                            <li>Try a different USB port</li>
                        </ul>
                    </div>
                `;
                errorDiv.classList.remove('hidden');
            }
        });

        // Write form
        document.getElementById('writeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Clear previous results
            const resultDiv = document.getElementById('writeResult');
            resultDiv.classList.add('hidden');
            
            if (!isConnected) {
                resultDiv.className = 'mt-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded';
                resultDiv.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Not Connected:</strong> Please connect to Arduino first
                    </div>
                `;
                resultDiv.classList.remove('hidden');
                return;
            }
            
            const data = document.getElementById('writeData').value;
            const block = document.getElementById('writeBlock').value;
            const port = document.getElementById('portSelect').value;
            
            try {
                const response = await fetch('rfid_writer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=write&data=${encodeURIComponent(data)}&block=${block}&port=${encodeURIComponent(port)}`
                });
                
                const result = await response.json();
                
                resultDiv.className = result.status === 'success' ? 
                    'mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded' :
                    'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                
                resultDiv.innerHTML = `
                    <strong>${result.status === 'success' ? 'Success!' : 'Error!'}</strong><br>
                    ${result.message}
                `;
                resultDiv.classList.remove('hidden');
                
            } catch (error) {
                resultDiv.className = 'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                resultDiv.innerHTML = `<strong>Error!</strong><br>${error.message}`;
                resultDiv.classList.remove('hidden');
            }
        });

        // Read form
        document.getElementById('readForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Clear previous results
            const resultDiv = document.getElementById('readResult');
            resultDiv.classList.add('hidden');
            
            if (!isConnected) {
                resultDiv.className = 'mt-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded';
                resultDiv.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Not Connected:</strong> Please connect to Arduino first
                    </div>
                `;
                resultDiv.classList.remove('hidden');
                return;
            }
            
            const block = document.getElementById('readBlock').value;
            const port = document.getElementById('portSelect').value;
            
            try {
                const response = await fetch('rfid_writer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=read&block=${block}&port=${encodeURIComponent(port)}`
                });
                
                const result = await response.json();
                
                resultDiv.className = result.status === 'success' ? 
                    'mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded' :
                    'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                
                if (result.status === 'success') {
                    resultDiv.innerHTML = `
                        <strong>Success!</strong><br>
                        ${result.message}<br>
                        <strong>Data:</strong> ${result.data}<br>
                        <strong>Block:</strong> ${result.block}
                    `;
                } else {
                    resultDiv.innerHTML = `<strong>Error!</strong><br>${result.message}`;
                }
                
                resultDiv.classList.remove('hidden');
                
            } catch (error) {
                resultDiv.className = 'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                resultDiv.innerHTML = `<strong>Error!</strong><br>${error.message}`;
                resultDiv.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
