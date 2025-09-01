<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Sync - SEENS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-black text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Navigation Button -->
            <div class="text-center mb-4">
                <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-lg transition-colors duration-300 shadow-lg hover:shadow-xl">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Registration
                </a>
            </div>
            
            <h1 class="text-3xl font-bold text-center mb-8">
                <i class="fas fa-sync-alt text-blue-500 mr-2"></i>
                Database Sync Module
            </h1>
            
            <!-- Progress Section (Hidden by default) -->
            <div id="progress-section" class="hidden mb-8">
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-xl font-semibold mb-4 text-blue-400">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Sync Progress
                    </h3>
                    
                    <!-- Overall Progress -->
                    <div class="mb-4">
                        <div class="flex justify-between mb-2">
                            <span class="text-sm text-gray-300">Overall Progress</span>
                            <span id="overall-progress-text" class="text-sm font-bold text-blue-400">0%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-3">
                            <div id="overall-progress-bar" class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <!-- Current Table Progress -->
                    <div class="mb-4">
                        <div class="flex justify-between mb-2">
                            <span id="current-table-name" class="text-sm text-gray-300">Preparing...</span>
                            <span id="current-progress-text" class="text-sm font-bold text-green-400">0/0</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div id="current-progress-bar" class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <!-- Current Status -->
                    <div class="text-sm text-gray-400">
                        <span id="current-status">Initializing sync...</span>
                    </div>
                </div>
            </div>
            
            <!-- Status Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-gray-800 rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4 text-blue-400">Local Database</h2>
                    <div id="local-status" class="space-y-2">
                        <div id="local-tables" class="text-sm">
                            <div class="text-gray-400">Loading...</div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-800 rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4 text-green-400">Online Database</h2>
                    <div id="online-status" class="space-y-2">
                        <div id="online-tables" class="text-sm">
                            <div class="text-gray-400">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sync Buttons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <button id="sync-local-to-online" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-lg transition-colors duration-300">
                    <i class="fas fa-upload mr-2"></i>
                    Sync Local → Online
                </button>
                
                <button id="sync-online-to-local" class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-6 rounded-lg transition-colors duration-300">
                    <i class="fas fa-download mr-2"></i>
                    Sync Online → Local
                </button>
            </div>
            
            <!-- Database Fix Button -->
            <div class="text-center mb-8">
                <button id="fix-database-tables" class="bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-lg transition-colors duration-300">
                    <i class="fas fa-wrench mr-2"></i>
                    Migrate Database Tables
                </button>
            </div>
            
            <!-- Refresh Button -->
            <div class="text-center">
                <button id="refresh-status" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-6 rounded-lg transition-colors duration-300">
                    <i class="fas fa-refresh mr-2"></i>
                    Refresh Status
                </button>
            </div>
            
            <!-- Log Area -->
            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4">Sync Log</h3>
                <div id="sync-log" class="bg-gray-800 rounded-lg p-4 h-64 overflow-y-auto text-sm">
                    <div class="text-gray-400">No sync operations performed yet.</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Load initial status
            loadStatus();
            
            // Sync Local to Online
            $('#sync-local-to-online').click(function() {
                startSync('local_to_online');
            });
            
            // Sync Online to Local
            $('#sync-online-to-local').click(function() {
                startSync('online_to_local');
            });
            
            // Refresh Status
            $('#refresh-status').click(function() {
                loadStatus();
                addLog('🔄 Status refreshed');
            });
            
            // Fix Database Tables
            $('#fix-database-tables').click(function() {
                Swal.fire({
                    title: 'Fix Database Tables',
                    text: 'This will rename all tables to add the "seens_" prefix. This action cannot be undone. Continue?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, fix tables!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fixDatabaseTables();
                    }
                });
            });
            
            function startSync(direction) {
                // Disable buttons and show progress
                $('#sync-local-to-online, #sync-online-to-local').prop('disabled', true);
                $('#progress-section').removeClass('hidden');
                
                // Reset progress
                updateProgress(0, 0, 0, 0, 'Initializing sync...');
                
                // Start sync with progress updates
                syncWithProgress(direction);
            }
            
            function syncWithProgress(direction) {
                const action = direction === 'local_to_online' ? 'sync_local_to_online' : 'sync_online_to_local';
                
                $.ajax({
                    url: 'database_sync.php',
                    type: 'POST',
                    data: { action: action },
                    success: function(response) {
                        try {
                            if (response && response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sync Successful',
                                    text: response.message,
                                    confirmButtonText: 'OK'
                                });
                                addLog('✅ ' + response.message);
                            } else {
                                let errorMessage = 'Failed to sync data';
                                if (response && response.message) {
                                    errorMessage = response.message;
                                } else if (response && response.error) {
                                    errorMessage = response.error;
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Sync Failed',
                                    text: errorMessage,
                                    confirmButtonText: 'OK'
                                });
                                addLog('❌ ' + errorMessage);
                            }
                        } catch (error) {
                            console.error('Error processing sync response:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Sync Error',
                                text: 'An unexpected error occurred during sync',
                                confirmButtonText: 'OK'
                            });
                            addLog('❌ Unexpected error during sync');
                        }
                        
                        // Hide progress and re-enable buttons
                        $('#progress-section').addClass('hidden');
                        $('#sync-local-to-online').prop('disabled', false).html('<i class="fas fa-upload mr-2"></i>Sync Local → Online');
                        $('#sync-online-to-local').prop('disabled', false).html('<i class="fas fa-download mr-2"></i>Sync Online → Local');
                        
                        loadStatus();
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to connect to server',
                            confirmButtonText: 'OK'
                        });
                        addLog('❌ Connection error');
                        
                        // Hide progress and re-enable buttons
                        $('#progress-section').addClass('hidden');
                        $('#sync-local-to-online').prop('disabled', false).html('<i class="fas fa-upload mr-2"></i>Sync Local → Online');
                        $('#sync-online-to-local').prop('disabled', false).html('<i class="fas fa-download mr-2"></i>Sync Online → Local');
                    }
                });
                
                // Start progress polling
                pollProgress(direction);
            }
            
            function pollProgress(direction) {
                const progressInterval = setInterval(function() {
                    $.ajax({
                        url: 'database_sync.php',
                        type: 'POST',
                        data: { action: 'get_progress' },
                        success: function(response) {
                            if (response && response.progress) {
                                const progress = response.progress;
                                updateProgress(
                                    progress.overall_percent || 0,
                                    progress.current_table || '',
                                    progress.current_record || 0,
                                    progress.total_records || 0,
                                    progress.status || 'Syncing...'
                                );
                                
                                // Stop polling if sync is complete
                                if (progress.overall_percent >= 100) {
                                    clearInterval(progressInterval);
                                }
                            }
                        },
                        error: function() {
                            // Stop polling on error
                            clearInterval(progressInterval);
                        }
                    });
                }, 500); // Poll every 500ms
            }
            
            function updateProgress(overallPercent, currentTable, currentRecord, totalRecords, status) {
                // Update overall progress
                $('#overall-progress-bar').css('width', overallPercent + '%');
                $('#overall-progress-text').text(overallPercent + '%');
                
                // Update current table progress
                $('#current-table-name').text(currentTable || 'Preparing...');
                $('#current-progress-text').text(currentRecord + '/' + totalRecords);
                
                const currentPercent = totalRecords > 0 ? (currentRecord / totalRecords) * 100 : 0;
                $('#current-progress-bar').css('width', currentPercent + '%');
                
                // Update status
                $('#current-status').text(status);
            }
            
            function loadStatus() {
                $.ajax({
                    url: 'database_sync.php',
                    type: 'POST',
                    data: { action: 'get_status' },
                    success: function(response) {
                        try {
                            // Check if response has the expected structure
                            if (response && response.local && response.online) {
                                // Filter SEENS tables only
                                const seensTables = ['seens_account', 'seens_adviser', 'seens_logs', 'seens_student', 'seens_visitors'];
                                
                                // Display local SEENS tables
                                let localHtml = '';
                                seensTables.forEach(table => {
                                    if (response.local[table] !== undefined) {
                                        localHtml += `<div class="flex justify-between mb-1">
                                            <span class="text-gray-300">${table}:</span>
                                            <span class="font-bold text-blue-400">${response.local[table]}</span>
                                        </div>`;
                                    }
                                });
                                $('#local-tables').html(localHtml || '<div class="text-gray-400">No SEENS tables found</div>');
                                
                                // Display online SEENS tables
                                let onlineHtml = '';
                                seensTables.forEach(table => {
                                    if (response.online[table] !== undefined) {
                                        onlineHtml += `<div class="flex justify-between mb-1">
                                            <span class="text-gray-300">${table}:</span>
                                            <span class="font-bold text-green-400">${response.online[table]}</span>
                                        </div>`;
                                    }
                                });
                                $('#online-tables').html(onlineHtml || '<div class="text-gray-400">No SEENS tables found</div>');
                            } else {
                                // Handle unexpected response structure
                                $('#local-tables').html('<div class="text-gray-400">N/A</div>');
                                $('#online-tables').html('<div class="text-gray-400">N/A</div>');
                                console.error('Unexpected response structure:', response);
                            }
                        } catch (error) {
                            console.error('Error processing status response:', error);
                            $('#local-tables, #online-tables').html('<div class="text-gray-400">Error</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        $('#local-tables, #online-tables').html('<div class="text-gray-400">Error</div>');
                    }
                });
            }
            
            function fixDatabaseTables() {
                // Disable button and show loading state
                $('#fix-database-tables').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Fixing Tables...');
                
                $.ajax({
                    url: 'database_sync.php',
                    type: 'POST',
                    data: { action: 'fix_database_tables' },
                    success: function(response) {
                        try {
                            if (response && response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Tables Fixed Successfully',
                                    text: response.message,
                                    confirmButtonText: 'OK'
                                });
                                addLog('✅ ' + response.message);
                            } else {
                                let errorMessage = 'Failed to fix database tables';
                                if (response && response.message) {
                                    errorMessage = response.message;
                                } else if (response && response.error) {
                                    errorMessage = response.error;
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Fix Failed',
                                    text: errorMessage,
                                    confirmButtonText: 'OK'
                                });
                                addLog('❌ ' + errorMessage);
                            }
                        } catch (error) {
                            console.error('Error processing fix response:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Fix Error',
                                text: 'An unexpected error occurred while fixing tables',
                                confirmButtonText: 'OK'
                            });
                            addLog('❌ Unexpected error while fixing tables');
                        }
                        
                        // Re-enable button
                        $('#fix-database-tables').prop('disabled', false).html('<i class="fas fa-wrench mr-2"></i>Migrate Tables');
                        
                        // Refresh status
                        loadStatus();
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to connect to server',
                            confirmButtonText: 'OK'
                        });
                        addLog('❌ Connection error while fixing tables');
                        
                        // Re-enable button
                        $('#fix-database-tables').prop('disabled', false).html('<i class="fas fa-wrench mr-2"></i>Migrate Tables');
                    }
                });
            }
            
            function addLog(message) {
                const timestamp = new Date().toLocaleTimeString();
                const logEntry = `<div class="mb-2"><span class="text-gray-400">[${timestamp}]</span> ${message}</div>`;
                $('#sync-log').append(logEntry);
                $('#sync-log').scrollTop($('#sync-log')[0].scrollHeight);
            }
        });
    </script>
</body>
</html>
