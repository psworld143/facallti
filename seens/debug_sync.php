<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Sync Interface</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-black text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-center mb-8">
                <i class="fas fa-bug text-red-500 mr-2"></i>
                Debug Sync Interface
            </h1>
            
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
            
            <!-- Debug Info -->
            <div class="bg-gray-800 rounded-lg p-6 mb-8">
                <h3 class="text-xl font-semibold mb-4">Debug Information</h3>
                <div id="debug-info" class="text-sm">
                    <div class="text-gray-400">Loading debug info...</div>
                </div>
            </div>
            
            <!-- Raw Response -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h3 class="text-xl font-semibold mb-4">Raw Response</h3>
                <pre id="raw-response" class="text-xs bg-gray-900 p-4 rounded overflow-auto max-h-64">Loading...</pre>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            console.log('Debug interface loaded');
            loadStatus();
            
            function loadStatus() {
                console.log('Loading status...');
                $('#debug-info').html('<div class="text-yellow-400">Making AJAX request...</div>');
                
                $.ajax({
                    url: 'database_sync.php',
                    type: 'POST',
                    data: { action: 'get_status' },
                    success: function(response) {
                        console.log('AJAX success:', response);
                        $('#raw-response').text(JSON.stringify(response, null, 2));
                        
                        try {
                            $('#debug-info').html('<div class="text-green-400">AJAX request successful</div>');
                            
                            // Check if response has the expected structure
                            if (response && response.local && response.online) {
                                $('#debug-info').append('<div class="text-green-400 mt-2">Response structure is valid</div>');
                                
                                // Count tables
                                const localTableCount = Object.keys(response.local).length;
                                const onlineTableCount = Object.keys(response.online).length;
                                
                                $('#debug-info').append(`<div class="text-blue-400 mt-2">Local tables: ${localTableCount}</div>`);
                                $('#debug-info').append(`<div class="text-green-400 mt-2">Online tables: ${onlineTableCount}</div>`);
                                
                                // Display local tables
                                let localHtml = '';
                                for (let table in response.local) {
                                    localHtml += `<div class="flex justify-between mb-1">
                                        <span class="text-gray-300">${table}:</span>
                                        <span class="font-bold text-blue-400">${response.local[table]}</span>
                                    </div>`;
                                }
                                $('#local-tables').html(localHtml || '<div class="text-gray-400">No tables found</div>');
                                
                                // Display online tables
                                let onlineHtml = '';
                                for (let table in response.online) {
                                    onlineHtml += `<div class="flex justify-between mb-1">
                                        <span class="text-gray-300">${table}:</span>
                                        <span class="font-bold text-green-400">${response.online[table]}</span>
                                    </div>`;
                                }
                                $('#online-tables').html(onlineHtml || '<div class="text-gray-400">No tables found</div>');
                                
                                $('#debug-info').append('<div class="text-green-400 mt-2">Tables displayed successfully</div>');
                            } else {
                                $('#debug-info').append('<div class="text-red-400 mt-2">Invalid response structure</div>');
                                $('#local-tables').html('<div class="text-gray-400">N/A</div>');
                                $('#online-tables').html('<div class="text-gray-400">N/A</div>');
                                console.error('Unexpected response structure:', response);
                            }
                        } catch (error) {
                            console.error('Error processing status response:', error);
                            $('#debug-info').append(`<div class="text-red-400 mt-2">Error: ${error.message}</div>`);
                            $('#local-tables, #online-tables').html('<div class="text-gray-400">Error</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        $('#debug-info').html(`<div class="text-red-400">AJAX Error: ${error}</div>`);
                        $('#raw-response').text(`Error: ${error}\nStatus: ${status}\nResponse: ${xhr.responseText}`);
                        $('#local-tables, #online-tables').html('<div class="text-gray-400">Error</div>');
                    }
                });
            }
        });
    </script>
</body>
</html>
