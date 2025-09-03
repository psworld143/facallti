<?php
session_start();
require_once '../config/database.php';
require_once '../includes/unified-error-handler.php';

// Use the existing check_admin function
require_once '../includes/functions.php';
check_admin();

$page_title = 'Database Synchronization';

// Include the new admin header
include 'includes/admin-header.php';
?>
    <style>
        .sync-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .sync-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #FF6B35;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        .status-online { background-color: #10B981; }
        .status-offline { background-color: #EF4444; }
        .status-syncing { background-color: #F59E0B; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .progress-bar {
            transition: width 0.3s ease;
            background: linear-gradient(90deg, #FF6B35, #F59E0B);
        }
        
        .log-container {
            max-height: 400px;
            overflow-y: auto;
            background: #1a1a1a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .log-entry {
            padding: 2px 0;
            border-bottom: 1px solid #333;
        }
        
        .log-success { color: #00ff00; }
        .log-error { color: #ff4444; }
        .log-warning { color: #ffaa00; }
        .log-info { color: #00aaff; }
        
        .comparison-table {
            font-size: 11px;
        }
        
        .comparison-table th,
        .comparison-table td {
            padding: 6px 8px;
        }
        
        .match { background-color: #dcfce7; color: #166534; }
        .mismatch { background-color: #fef2f2; color: #991b1b; }
        .missing { background-color: #fef3c7; color: #92400e; }
    </style>

            <div class="p-6">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-seait-orange to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-sync-alt text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Database Synchronization</h1>
                            <p class="text-gray-600">Manage and synchronize local and online databases</p>
                        </div>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Local Database Status -->
                        <div class="sync-card rounded-xl p-6 shadow-lg">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Local Database</h3>
                                <div class="status-indicator status-online" id="localStatus"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Host:</span>
                                    <span class="font-medium">localhost</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Database:</span>
                                    <span class="font-medium">seait_website</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tables:</span>
                                    <span class="font-medium" id="localTables">-</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-medium text-green-600" id="localStatusText">Checking...</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Online Database Status -->
                        <div class="sync-card rounded-xl p-6 shadow-lg">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Online Database</h3>
                                <div class="status-indicator status-online" id="onlineStatus"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Host:</span>
                                    <span class="font-medium">seait-edu.ph</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Database:</span>
                                    <span class="font-medium">seaitedu_seait_website</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tables:</span>
                                    <span class="font-medium" id="onlineTables">-</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-medium text-green-600" id="onlineStatusText">Checking...</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sync Status -->
                        <div class="sync-card rounded-xl p-6 shadow-lg">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Sync Status</h3>
                                <div class="status-indicator status-offline" id="syncStatus"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Last Sync:</span>
                                    <span class="font-medium" id="lastSync">Never</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Differences:</span>
                                    <span class="font-medium" id="differences">-</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Mode:</span>
                                    <span class="font-medium text-blue-600">Safe (Preserve Data)</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-medium text-gray-600" id="syncStatusText">Ready</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <button id="checkConnectionBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-plug mr-2"></i>Check Connections
                    </button>
                    
                    <button id="compareBtn" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-balance-scale mr-2"></i>Compare Databases
                    </button>
                    
                    <button id="syncBtn" class="bg-seait-orange hover:bg-orange-600 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-sync-alt mr-2"></i>Start Safe Sync
                    </button>
                </div>
                
                <!-- Progress Bar -->
                <div id="progressContainer" class="mb-6 hidden">
                    <div class="bg-gray-200 rounded-full h-4 overflow-hidden shadow-inner">
                        <div id="progressBar" class="progress-bar h-full rounded-full" style="width: 0%"></div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600 mt-2">
                        <span id="progressText">Ready to start...</span>
                        <span id="progressPercent">0%</span>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6">
                            <button class="tab-btn active py-4 px-2 border-b-2 border-seait-orange text-seait-orange font-medium" data-tab="comparison">
                                <i class="fas fa-table mr-2"></i>Database Comparison
                            </button>
                            <button class="tab-btn py-4 px-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium" data-tab="logs">
                                <i class="fas fa-terminal mr-2"></i>Sync Logs
                            </button>
                            <button class="tab-btn py-4 px-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium" data-tab="settings">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </button>
                        </nav>
                    </div>
                    
                    <!-- Tab Content -->
                    <div class="p-6">
                        <!-- Database Comparison Tab -->
                        <div id="comparison-tab" class="tab-content">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Database Structure Comparison</h3>
                                <p class="text-gray-600 text-sm">Compare table structures and data between local and online databases.</p>
                            </div>
                            
                            <div id="comparisonResults" class="space-y-4">
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-database text-4xl mb-4"></i>
                                    <p>Click "Compare Databases" to see detailed comparison results</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sync Logs Tab -->
                        <div id="logs-tab" class="tab-content hidden">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Synchronization Logs</h3>
                                <p class="text-gray-600 text-sm">Real-time logs of database synchronization operations.</p>
                            </div>
                            
                            <div class="log-container rounded-lg p-4" id="logContainer">
                                <div class="log-entry log-info">[INFO] Database Sync UI initialized</div>
                                <div class="log-entry log-info">[INFO] Ready to perform operations</div>
                            </div>
                            
                            <div class="mt-4 flex space-x-2">
                                <button id="clearLogsBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                                    <i class="fas fa-trash mr-1"></i>Clear Logs
                                </button>
                                <button id="downloadLogsBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                                    <i class="fas fa-download mr-1"></i>Download Logs
                                </button>
                            </div>
                        </div>
                        
                        <!-- Settings Tab -->
                        <div id="settings-tab" class="tab-content hidden">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Synchronization Settings</h3>
                                <p class="text-gray-600 text-sm">Configure database synchronization preferences.</p>
                            </div>
                            
                            <div class="space-y-6">
                                <!-- Sync Mode -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-800 mb-3">Synchronization Mode</h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="radio" name="syncMode" value="safe" checked class="mr-3">
                                            <div>
                                                <span class="font-medium text-green-600">Safe Mode (Recommended)</span>
                                                <p class="text-sm text-gray-600">Preserves existing online data, only adds missing tables and structures</p>
                                            </div>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="syncMode" value="full" class="mr-3">
                                            <div>
                                                <span class="font-medium text-red-600">Full Sync (Destructive)</span>
                                                <p class="text-sm text-gray-600">Completely replaces online database with local data</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Backup Options -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-800 mb-3">Backup Options</h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" checked class="mr-3">
                                            <span>Create backup before sync</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" checked class="mr-3">
                                            <span>Verify data integrity after sync</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" class="mr-3">
                                            <span>Send email notification on completion</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Connection Settings -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-800 mb-3">Connection Settings</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Connection Timeout</label>
                                            <select class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                                <option>30 seconds</option>
                                                <option selected>60 seconds</option>
                                                <option>120 seconds</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Retry Attempts</label>
                                            <select class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                                <option>1</option>
                                                <option selected>3</option>
                                                <option>5</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Database Synchronization</h3>
                <p class="text-gray-600 text-sm">This will synchronize the local database to the online database. Are you sure you want to continue?</p>
            </div>
            
            <div class="flex space-x-3">
                <button id="cancelSync" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
                    Cancel
                </button>
                <button id="confirmSync" class="flex-1 bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium">
                    Start Sync
                </button>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                
                // Update button states
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('active', 'border-seait-orange', 'text-seait-orange');
                    b.classList.add('border-transparent', 'text-gray-500');
                });
                btn.classList.add('active', 'border-seait-orange', 'text-seait-orange');
                btn.classList.remove('border-transparent', 'text-gray-500');
                
                // Update content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById(tabId + '-tab').classList.remove('hidden');
            });
        });
        
        // Logging function
        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry log-${type}`;
            logEntry.textContent = `[${timestamp}] [${type.toUpperCase()}] ${message}`;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // Progress update function
        function updateProgress(percent, text) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressPercent = document.getElementById('progressPercent');
            const progressContainer = document.getElementById('progressContainer');
            
            progressContainer.classList.remove('hidden');
            progressBar.style.width = percent + '%';
            progressText.textContent = text;
            progressPercent.textContent = percent + '%';
        }
        
        // Check connections
        document.getElementById('checkConnectionBtn').addEventListener('click', async () => {
            addLog('Checking database connections...');
            updateProgress(10, 'Checking local database...');
            
            try {
                const response = await fetch('api/check-db-connections.php');
                const data = await response.json();
                
                updateProgress(50, 'Checking online database...');
                
                // Update local status
                const localStatus = document.getElementById('localStatus');
                const localStatusText = document.getElementById('localStatusText');
                const localTables = document.getElementById('localTables');
                
                if (data.local.connected) {
                    localStatus.className = 'status-indicator status-online';
                    localStatusText.textContent = 'Connected';
                    localStatusText.className = 'font-medium text-green-600';
                    localTables.textContent = data.local.tables || '-';
                    addLog('Local database connection successful');
                } else {
                    localStatus.className = 'status-indicator status-offline';
                    localStatusText.textContent = 'Disconnected';
                    localStatusText.className = 'font-medium text-red-600';
                    addLog('Local database connection failed', 'error');
                }
                
                updateProgress(80, 'Updating status...');
                
                // Update online status
                const onlineStatus = document.getElementById('onlineStatus');
                const onlineStatusText = document.getElementById('onlineStatusText');
                const onlineTables = document.getElementById('onlineTables');
                
                if (data.online.connected) {
                    onlineStatus.className = 'status-indicator status-online';
                    onlineStatusText.textContent = 'Connected';
                    onlineStatusText.className = 'font-medium text-green-600';
                    onlineTables.textContent = data.online.tables || '-';
                    addLog('Online database connection successful');
                } else {
                    onlineStatus.className = 'status-indicator status-offline';
                    onlineStatusText.textContent = 'Disconnected';
                    onlineStatusText.className = 'font-medium text-red-600';
                    addLog('Online database connection failed', 'error');
                }
                
                updateProgress(100, 'Connection check complete');
                
                setTimeout(() => {
                    document.getElementById('progressContainer').classList.add('hidden');
                }, 2000);
                
            } catch (error) {
                addLog('Error checking connections: ' + error.message, 'error');
                updateProgress(0, 'Connection check failed');
            }
        });
        
        // Compare databases
        document.getElementById('compareBtn').addEventListener('click', async () => {
            addLog('Starting database comparison...');
            updateProgress(10, 'Comparing table structures...');
            
            try {
                const response = await fetch('api/compare-databases.php');
                const data = await response.json();
                
                updateProgress(50, 'Analyzing differences...');
                
                // Update comparison results
                const comparisonResults = document.getElementById('comparisonResults');
                comparisonResults.innerHTML = generateComparisonHTML(data);
                
                // Update differences count
                const differences = document.getElementById('differences');
                differences.textContent = data.total_differences || '0';
                
                updateProgress(100, 'Comparison complete');
                addLog('Database comparison completed');
                
                setTimeout(() => {
                    document.getElementById('progressContainer').classList.add('hidden');
                }, 2000);
                
            } catch (error) {
                addLog('Error comparing databases: ' + error.message, 'error');
                updateProgress(0, 'Comparison failed');
            }
        });
        
        // Generate comparison HTML
        function generateComparisonHTML(data) {
            return `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 text-xl mr-3"></i>
                            <div>
                                <h4 class="font-semibold text-green-800">Tables Match</h4>
                                <p class="text-2xl font-bold text-green-600">${data.matches || 0}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-800">Differences</h4>
                                <p class="text-2xl font-bold text-yellow-600">${data.differences || 0}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-times-circle text-red-600 text-xl mr-3"></i>
                            <div>
                                <h4 class="font-semibold text-red-800">Missing Tables</h4>
                                <p class="text-2xl font-bold text-red-600">${data.missing || 0}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="comparison-table w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="text-left">Table Name</th>
                                <th class="text-center">Local</th>
                                <th class="text-center">Online</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action Needed</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${generateTableRows(data.tables || [])}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        function generateTableRows(tables) {
            return tables.map(table => {
                let statusClass = 'match';
                let statusText = '✅ Match';
                let actionText = 'None';
                
                if (table.status === 'missing_online') {
                    statusClass = 'missing';
                    statusText = '➕ Missing Online';
                    actionText = 'Create Table';
                } else if (table.status === 'different') {
                    statusClass = 'mismatch';
                    statusText = '⚠️ Different';
                    if (table.note) {
                        actionText = 'Fix: ' + table.note;
                    } else {
                        actionText = 'Update Structure';
                    }
                } else if (table.status === 'match' && table.note) {
                    statusText = '✅ Match*';
                    actionText = table.note;
                }
                
                return `
                    <tr>
                        <td class="font-medium">${table.name}</td>
                        <td class="text-center">${table.local ? '✅' : '❌'}</td>
                        <td class="text-center">${table.online ? '✅' : '❌'}</td>
                        <td class="text-center ${statusClass}">${statusText}</td>
                        <td class="text-center text-xs">${actionText}</td>
                    </tr>
                `;
            }).join('');
        }
        
        // Sync confirmation
        document.getElementById('syncBtn').addEventListener('click', () => {
            document.getElementById('confirmModal').classList.remove('hidden');
        });
        
        document.getElementById('cancelSync').addEventListener('click', () => {
            document.getElementById('confirmModal').classList.add('hidden');
        });
        
        document.getElementById('confirmSync').addEventListener('click', async () => {
            document.getElementById('confirmModal').classList.add('hidden');
            await startSync();
        });
        
        // Start synchronization
        async function startSync() {
            addLog('Starting database synchronization...');
            updateProgress(5, 'Initializing sync process...');
            
            const syncStatus = document.getElementById('syncStatus');
            const syncStatusText = document.getElementById('syncStatusText');
            
            syncStatus.className = 'status-indicator status-syncing';
            syncStatusText.textContent = 'Syncing...';
            syncStatusText.className = 'font-medium text-yellow-600';
            
            try {
                const response = await fetch('api/sync-databases.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        mode: 'safe',
                        preserve_data: true
                    })
                });
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n');
                    
                    for (const line of lines) {
                        if (line.trim()) {
                            try {
                                const data = JSON.parse(line);
                                if (data.progress) {
                                    updateProgress(data.progress, data.message);
                                }
                                if (data.log) {
                                    addLog(data.log.message, data.log.type);
                                }
                            } catch (e) {
                                // Handle non-JSON lines
                                if (line.includes('ERROR')) {
                                    addLog(line, 'error');
                                } else if (line.includes('SUCCESS')) {
                                    addLog(line, 'success');
                                } else {
                                    addLog(line, 'info');
                                }
                            }
                        }
                    }
                }
                
                syncStatus.className = 'status-indicator status-online';
                syncStatusText.textContent = 'Completed';
                syncStatusText.className = 'font-medium text-green-600';
                
                // Update last sync time
                document.getElementById('lastSync').textContent = new Date().toLocaleString();
                
                addLog('Database synchronization completed successfully!', 'success');
                
            } catch (error) {
                syncStatus.className = 'status-indicator status-offline';
                syncStatusText.textContent = 'Failed';
                syncStatusText.className = 'font-medium text-red-600';
                
                addLog('Synchronization failed: ' + error.message, 'error');
            }
        }
        
        // Clear logs
        document.getElementById('clearLogsBtn').addEventListener('click', () => {
            document.getElementById('logContainer').innerHTML = '';
            addLog('Logs cleared');
        });
        
        // Download logs
        document.getElementById('downloadLogsBtn').addEventListener('click', () => {
            const logs = document.getElementById('logContainer').textContent;
            const blob = new Blob([logs], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `database-sync-logs-${new Date().toISOString().slice(0, 10)}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        });
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', () => {
            addLog('Database Sync UI loaded successfully');
            // Auto-check connections on load
            setTimeout(() => {
                document.getElementById('checkConnectionBtn').click();
            }, 1000);
        });
    </script>
</body>
</html>
