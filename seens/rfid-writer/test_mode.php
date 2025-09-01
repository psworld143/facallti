<?php
/**
 * Test Mode for RFID Writer
 * Simulates Arduino responses when hardware is not connected
 */

require_once 'rfid_writer.php';

class RFIDWriterTestMode extends RFIDWriter {
    private $testMode = true;
    private $simulatedData = [];
    
    public function __construct($port = null, $baudRate = 9600, $timeout = 5) {
        parent::__construct($port, $baudRate, $timeout);
        $this->testMode = true;
    }
    
    /**
     * Override connect method for test mode
     */
    public function connect() {
        // Always return true in test mode
        return true;
    }
    
    /**
     * Override disconnect method for test mode
     */
    public function disconnect() {
        // Do nothing in test mode
        return true;
    }
    
    /**
     * Override getStatus method for test mode
     */
    public function getStatus() {
        return 'RFID_WRITER_READY';
    }
    
    /**
     * Override writeToCard method for test mode
     */
    public function writeToCard($data, $block = 4) {
        // Simulate writing data
        $this->simulatedData[$block] = $data;
        
        return [
            'status' => 'success',
            'message' => 'Data written successfully (TEST MODE)',
            'data' => $data,
            'block' => $block
        ];
    }
    
    /**
     * Override readFromCard method for test mode
     */
    public function readFromCard($block = 4) {
        if (isset($this->simulatedData[$block])) {
            return [
                'status' => 'success',
                'message' => 'Data read successfully (TEST MODE)',
                'data' => $this->simulatedData[$block],
                'block' => $block
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'No data found in block ' . $block . ' (TEST MODE)'
            ];
        }
    }
    
    /**
     * Get simulated data
     */
    public function getSimulatedData() {
        return $this->simulatedData;
    }
    
    /**
     * Clear simulated data
     */
    public function clearSimulatedData() {
        $this->simulatedData = [];
    }
}

// Test mode API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prevent any output before JSON response
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        $data = $_POST['data'] ?? '';
        $block = intval($_POST['block'] ?? 4);
        $port = $_POST['port'] ?? ((PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0');
        
        $rfid = new RFIDWriterTestMode($port);
        
        // Always connect successfully in test mode
        if (!$rfid->connect()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to connect to Arduino (TEST MODE)'
            ]);
            exit;
        }
        
        switch ($action) {
            case 'write':
                $result = $rfid->writeToCard($data, $block);
                break;
                
            case 'read':
                $result = $rfid->readFromCard($block);
                break;
                
            case 'status':
                $status = $rfid->getStatus();
                $result = [
                    'status' => 'success',
                    'message' => $status . ' (TEST MODE)'
                ];
                break;
                
            case 'clear_data':
                $rfid->clearSimulatedData();
                $result = [
                    'status' => 'success',
                    'message' => 'Simulated data cleared (TEST MODE)'
                ];
                break;
                
            case 'get_data':
                $simulatedData = $rfid->getSimulatedData();
                $result = [
                    'status' => 'success',
                    'message' => 'Simulated data retrieved (TEST MODE)',
                    'data' => $simulatedData
                ];
                break;
                
            default:
                $result = [
                    'status' => 'error',
                    'message' => 'Invalid action'
                ];
        }
        
        $rfid->disconnect();
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Exception: ' . $e->getMessage()
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEENS RFID Writer - Test Mode</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-credit-card text-blue-600"></i>
                SEENS RFID Writer - TEST MODE
            </h1>
            <p class="text-gray-600">Simulated RFID operations (no hardware required)</p>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mt-4">
                <strong>Test Mode Active:</strong> This is a simulation. No actual RFID hardware is required.
            </div>
        </div>

        <!-- Main Interface -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Write Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-edit text-blue-600"></i>
                    Write to RFID Card (Test Mode)
                </h2>
                <form id="writeForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Student ID Number</label>
                        <input type="text" id="writeData" name="data" placeholder="e.g., 2021-0001" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               maxlength="16">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Block Number</label>
                        <input type="number" id="writeBlock" name="block" value="4" min="0" max="63"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <i class="fas fa-save mr-2"></i>Write to Card (Test)
                    </button>
                </form>
                <div id="writeResult" class="mt-4 hidden"></div>
            </div>

            <!-- Read Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-eye text-green-600"></i>
                    Read from RFID Card (Test Mode)
                </h2>
                <form id="readForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Block Number</label>
                        <input type="number" id="readBlock" name="block" value="4" min="0" max="63"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-search mr-2"></i>Read from Card (Test)
                    </button>
                </form>
                <div id="readResult" class="mt-4 hidden"></div>
            </div>
        </div>

        <!-- Test Controls -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-cogs text-purple-600"></i>
                Test Controls
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button id="clearDataBtn" class="bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <i class="fas fa-trash mr-2"></i>Clear Test Data
                </button>
                <button id="getDataBtn" class="bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <i class="fas fa-database mr-2"></i>View Test Data
                </button>
                <button id="testConnectionBtn" class="bg-yellow-600 text-white py-2 px-4 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    <i class="fas fa-plug mr-2"></i>Test Connection
                </button>
            </div>
            <div id="testResult" class="mt-4 hidden"></div>
        </div>
    </div>

    <script>
        // Write form
        document.getElementById('writeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const data = document.getElementById('writeData').value;
            const block = document.getElementById('writeBlock').value;
            const resultDiv = document.getElementById('writeResult');
            
            try {
                const response = await fetch('test_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=write&data=${encodeURIComponent(data)}&block=${block}`
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
            
            const block = document.getElementById('readBlock').value;
            const resultDiv = document.getElementById('readResult');
            
            try {
                const response = await fetch('test_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=read&block=${block}`
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

        // Test controls
        document.getElementById('clearDataBtn').addEventListener('click', async function() {
            const resultDiv = document.getElementById('testResult');
            
            try {
                const response = await fetch('test_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clear_data'
                });
                
                const result = await response.json();
                
                resultDiv.className = result.status === 'success' ? 
                    'mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded' :
                    'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                
                resultDiv.innerHTML = `<strong>${result.status === 'success' ? 'Success!' : 'Error!'}</strong><br>${result.message}`;
                resultDiv.classList.remove('hidden');
                
            } catch (error) {
                resultDiv.className = 'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                resultDiv.innerHTML = `<strong>Error!</strong><br>${error.message}`;
                resultDiv.classList.remove('hidden');
            }
        });

        document.getElementById('getDataBtn').addEventListener('click', async function() {
            const resultDiv = document.getElementById('testResult');
            
            try {
                const response = await fetch('test_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_data'
                });
                
                const result = await response.json();
                
                resultDiv.className = result.status === 'success' ? 
                    'mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded' :
                    'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                
                if (result.status === 'success') {
                    resultDiv.innerHTML = `
                        <strong>Success!</strong><br>
                        ${result.message}<br>
                        <strong>Test Data:</strong><br>
                        <pre>${JSON.stringify(result.data, null, 2)}</pre>
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

        document.getElementById('testConnectionBtn').addEventListener('click', async function() {
            const resultDiv = document.getElementById('testResult');
            
            try {
                const response = await fetch('test_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=status'
                });
                
                const result = await response.json();
                
                resultDiv.className = result.status === 'success' ? 
                    'mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded' :
                    'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                
                resultDiv.innerHTML = `<strong>${result.status === 'success' ? 'Success!' : 'Error!'}</strong><br>${result.message}`;
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
