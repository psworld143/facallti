<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Card Reader/Writer - Python API Interface</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-connected { background-color: #10b981; }
        .status-disconnected { background-color: #ef4444; }
        .status-unknown { background-color: #6b7280; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">RFID Card Reader/Writer</h1>
                <p class="text-gray-600">Python API Interface - More Reliable Communication</p>
                <div class="mt-4">
                    <span class="status-indicator" id="apiStatus"></span>
                    <span id="apiStatusText" class="text-sm font-medium">Checking API status...</span>
                </div>
            </div>

            <!-- Connection Panel -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Connection</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="portSelect" class="block text-sm font-medium text-gray-700 mb-2">Serial Port</label>
                        <select id="portSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Port</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Connection Status</label>
                        <div class="flex items-center">
                            <span class="status-indicator status-unknown" id="connectionStatus"></span>
                            <span id="connectionStatusText" class="text-sm font-medium">Not Connected</span>
                        </div>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button id="connectBtn" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Connect
                        </button>
                        <button id="disconnectBtn" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500" disabled>
                            Disconnect
                        </button>
                    </div>
                </div>
                
                <div class="flex space-x-2">
                    <button id="refreshPortsBtn" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        Refresh Ports
                    </button>
                    <button id="testConnectionBtn" class="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500" disabled>
                        Test Connection
                    </button>
                </div>
            </div>

            <!-- RFID Operations Panel -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">RFID Operations</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Read UID Panel -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-gray-800 mb-3">Read Card UID</h3>
                        <p class="text-sm text-gray-600 mb-4">Place an RFID card on the reader and click the button below to read its UID.</p>
                        <button id="readUidBtn" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500" disabled>
                            Read UID
                        </button>
                        <div id="uidResult" class="mt-3 p-3 bg-gray-50 rounded-md hidden">
                            <div class="text-sm">
                                <strong>UID:</strong> <span id="uidValue" class="font-mono"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Read Data Panel -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-gray-800 mb-3">Read Data from Block</h3>
                        <div class="mb-3">
                            <label for="readBlockInput" class="block text-sm font-medium text-gray-700 mb-1">Block Number</label>
                            <input type="number" id="readBlockInput" value="4" min="0" max="63" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button id="readDataBtn" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500" disabled>
                            Read Data
                        </button>
                        <div id="readResult" class="mt-3 p-3 bg-gray-50 rounded-md hidden">
                            <div class="text-sm">
                                <strong>Data:</strong> <span id="readDataValue" class="font-mono"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Write Data Panel -->
                <div class="mt-6 border border-gray-200 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">Write Data to Block</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                        <div>
                            <label for="writeBlockInput" class="block text-sm font-medium text-gray-700 mb-1">Block Number</label>
                            <input type="number" id="writeBlockInput" value="4" min="0" max="63" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="writeDataInput" class="block text-sm font-medium text-gray-700 mb-1">Data to Write</label>
                            <input type="text" id="writeDataInput" placeholder="Enter data (max 16 chars)" maxlength="16" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button id="writeDataBtn" class="w-full bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500" disabled>
                                Write Data
                            </button>
                        </div>
                    </div>
                    <div id="writeResult" class="mt-3 p-3 bg-gray-50 rounded-md hidden">
                        <div class="text-sm">
                            <strong>Result:</strong> <span id="writeResultValue"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log Panel -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Activity Log</h2>
                <div id="logContainer" class="bg-gray-900 text-green-400 p-4 rounded-md font-mono text-sm h-64 overflow-y-auto">
                    <div class="text-gray-400">System ready. Check API status first.</div>
                </div>
                <div class="mt-3 flex space-x-2">
                    <button id="clearLogBtn" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">
                        Clear Log
                    </button>
                    <button id="exportLogBtn" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                        Export Log
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        class RFIDInterface {
            constructor() {
                this.apiBaseUrl = 'php_rfid_interface.php';
                this.isConnected = false;
                this.currentPort = null;
                this.initEventListeners();
                this.checkAPIStatus();
            }
            
            initEventListeners() {
                // Connection buttons
                document.getElementById('connectBtn').addEventListener('click', () => this.connect());
                document.getElementById('disconnectBtn').addEventListener('click', () => this.disconnect());
                document.getElementById('refreshPortsBtn').addEventListener('click', () => this.refreshPorts());
                document.getElementById('testConnectionBtn').addEventListener('click', () => this.testConnection());
                
                // RFID operation buttons
                document.getElementById('readUidBtn').addEventListener('click', () => this.readUID());
                document.getElementById('readDataBtn').addEventListener('click', () => this.readData());
                document.getElementById('writeDataBtn').addEventListener('click', () => this.writeData());
                
                // Log buttons
                document.getElementById('clearLogBtn').addEventListener('click', () => this.clearLog());
                document.getElementById('exportLogBtn').addEventListener('click', () => this.exportLog());
            }
            
            log(message, type = 'info') {
                const logContainer = document.getElementById('logContainer');
                const timestamp = new Date().toLocaleTimeString();
                const colorClass = type === 'error' ? 'text-red-400' : type === 'success' ? 'text-green-400' : 'text-yellow-400';
                
                const logEntry = document.createElement('div');
                logEntry.className = `mb-1 ${colorClass}`;
                logEntry.textContent = `[${timestamp}] ${message}`;
                
                logContainer.appendChild(logEntry);
                logContainer.scrollTop = logContainer.scrollHeight;
            }
            
            async makeRequest(action, data = {}) {
                const formData = new FormData();
                formData.append('action', action);
                
                for (const [key, value] of Object.entries(data)) {
                    formData.append(key, value);
                }
                
                try {
                    const response = await fetch(this.apiBaseUrl, {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    return await response.json();
                } catch (error) {
                    this.log(`Request error: ${error.message}`, 'error');
                    return { success: false, error: error.message };
                }
            }
            
            async checkAPIStatus() {
                this.log('Checking Python API status...');
                
                const result = await this.makeRequest('check_api');
                const statusIndicator = document.getElementById('apiStatus');
                const statusText = document.getElementById('apiStatusText');
                
                if (result.success) {
                    statusIndicator.className = 'status-indicator status-connected';
                    statusText.textContent = 'Python API is running';
                    this.log('Python API is running and responding', 'success');
                    this.refreshPorts();
                } else {
                    statusIndicator.className = 'status-indicator status-disconnected';
                    statusText.textContent = 'Python API is not responding';
                    this.log(`Python API error: ${result.error}`, 'error');
                    this.log('Please start the Python API server first', 'error');
                }
            }
            
            async refreshPorts() {
                this.log('Refreshing available ports...');
                
                const result = await this.makeRequest('ports');
                const portSelect = document.getElementById('portSelect');
                
                // Clear existing options
                portSelect.innerHTML = '<option value="">Select Port</option>';
                
                if (result.success && result.ports.length > 0) {
                    result.ports.forEach(port => {
                        const option = document.createElement('option');
                        option.value = port.device;
                        option.textContent = `${port.device} - ${port.description}`;
                        portSelect.appendChild(option);
                    });
                    this.log(`Found ${result.ports.length} available ports`, 'success');
                } else {
                    this.log('No serial ports found or error occurred', 'error');
                }
            }
            
            async connect() {
                const portSelect = document.getElementById('portSelect');
                const port = portSelect.value;
                
                if (!port) {
                    this.log('Please select a port first', 'error');
                    return;
                }
                
                this.log(`Attempting to connect to ${port}...`);
                
                const result = await this.makeRequest('connect', { port });
                
                if (result.success) {
                    this.isConnected = true;
                    this.currentPort = port;
                    this.updateConnectionUI(true);
                    this.log(`Successfully connected to ${port}`, 'success');
                } else {
                    this.log(`Connection failed: ${result.error}`, 'error');
                }
            }
            
            async disconnect() {
                this.log('Disconnecting...');
                
                const result = await this.makeRequest('disconnect');
                
                if (result.success) {
                    this.isConnected = false;
                    this.currentPort = null;
                    this.updateConnectionUI(false);
                    this.log('Disconnected from Arduino', 'success');
                } else {
                    this.log(`Disconnect failed: ${result.error}`, 'error');
                }
            }
            
            async testConnection() {
                this.log('Testing Arduino connection...');
                
                const result = await this.makeRequest('test');
                
                if (result.success) {
                    this.log('Arduino is responding correctly', 'success');
                } else {
                    this.log(`Connection test failed: ${result.error}`, 'error');
                }
            }
            
            async readUID() {
                this.log('Reading card UID...');
                
                const result = await this.makeRequest('read_uid');
                
                if (result.success) {
                    document.getElementById('uidValue').textContent = result.uid;
                    document.getElementById('uidResult').classList.remove('hidden');
                    this.log(`UID read successfully: ${result.uid}`, 'success');
                } else {
                    this.log(`UID reading failed: ${result.error}`, 'error');
                    if (result.raw_response) {
                        this.log(`Raw response: ${result.raw_response}`, 'info');
                    }
                }
            }
            
            async readData() {
                const blockNumber = document.getElementById('readBlockInput').value;
                
                if (!blockNumber) {
                    this.log('Please enter a block number', 'error');
                    return;
                }
                
                this.log(`Reading data from block ${blockNumber}...`);
                
                const result = await this.makeRequest('read', { block_number: blockNumber });
                
                if (result.success) {
                    document.getElementById('readDataValue').textContent = result.data;
                    document.getElementById('readResult').classList.remove('hidden');
                    this.log(`Data read successfully from block ${blockNumber}: ${result.data}`, 'success');
                } else {
                    this.log(`Data reading failed: ${result.error}`, 'error');
                    if (result.raw_response) {
                        this.log(`Raw response: ${result.raw_response}`, 'info');
                    }
                }
            }
            
            async writeData() {
                const blockNumber = document.getElementById('writeBlockInput').value;
                const data = document.getElementById('writeDataInput').value;
                
                if (!blockNumber || !data) {
                    this.log('Please enter both block number and data', 'error');
                    return;
                }
                
                this.log(`Writing data to block ${blockNumber}: ${data}`);
                
                const result = await this.makeRequest('write', { 
                    block_number: blockNumber, 
                    data: data 
                });
                
                if (result.success) {
                    document.getElementById('writeResultValue').textContent = result.message;
                    document.getElementById('writeResult').classList.remove('hidden');
                    this.log(`Data written successfully to block ${blockNumber}`, 'success');
                } else {
                    this.log(`Data writing failed: ${result.error}`, 'error');
                    if (result.raw_response) {
                        this.log(`Raw response: ${result.raw_response}`, 'info');
                    }
                }
            }
            
            updateConnectionUI(connected) {
                const connectBtn = document.getElementById('connectBtn');
                const disconnectBtn = document.getElementById('disconnectBtn');
                const testBtn = document.getElementById('testConnectionBtn');
                const readUidBtn = document.getElementById('readUidBtn');
                const readDataBtn = document.getElementById('readDataBtn');
                const writeDataBtn = document.getElementById('writeDataBtn');
                const statusIndicator = document.getElementById('connectionStatus');
                const statusText = document.getElementById('connectionStatusText');
                
                if (connected) {
                    connectBtn.disabled = true;
                    disconnectBtn.disabled = false;
                    testBtn.disabled = false;
                    readUidBtn.disabled = false;
                    readDataBtn.disabled = false;
                    writeDataBtn.disabled = false;
                    
                    statusIndicator.className = 'status-indicator status-connected';
                    statusText.textContent = `Connected to ${this.currentPort}`;
                } else {
                    connectBtn.disabled = false;
                    disconnectBtn.disabled = true;
                    testBtn.disabled = true;
                    readUidBtn.disabled = true;
                    readDataBtn.disabled = true;
                    writeDataBtn.disabled = true;
                    
                    statusIndicator.className = 'status-indicator status-disconnected';
                    statusText.textContent = 'Not Connected';
                }
            }
            
            clearLog() {
                const logContainer = document.getElementById('logContainer');
                logContainer.innerHTML = '<div class="text-gray-400">Log cleared.</div>';
            }
            
            exportLog() {
                const logContainer = document.getElementById('logContainer');
                const logText = logContainer.textContent;
                const blob = new Blob([logText], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `rfid_log_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.txt`;
                a.click();
                URL.revokeObjectURL(url);
            }
        }
        
        // Initialize the interface when page loads
        document.addEventListener('DOMContentLoaded', () => {
            new RFIDInterface();
        });
    </script>
</body>
</html>
