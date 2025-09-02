<?php
/**
 * Manual RFID Services Startup Script
 * Run this file directly to start RFID services manually
 */

// Include the service manager
require_once 'rfid_service_manager.php';

// Create a new instance and start services
$manager = new RFIDServiceManager();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>RFID Services Startup</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='assets/font-awesome/css/font-awesome.min.css'>
</head>
<body class='bg-gray-100 min-h-screen p-8'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-6 mb-6'>
            <h1 class='text-2xl font-bold text-gray-800 mb-4 flex items-center'>
                <i class='fa fa-microchip text-orange-500 mr-3'></i>
                RFID Services Startup
            </h1>
            
            <div class='space-y-4'>";

// Check current status
$status = $manager->getServiceStatus();
echo "<div class='bg-gray-50 rounded-lg p-4'>
    <h3 class='font-semibold text-gray-800 mb-2'>Current Status:</h3>
    <div class='grid grid-cols-1 md:grid-cols-2 gap-4'>
        <div class='flex items-center justify-between'>
            <span>Python API:</span>
            <span class='px-2 py-1 rounded text-sm " . ($status['python_api']['running'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . "'>
                " . ($status['python_api']['running'] ? 'Running' : 'Stopped') . "
            </span>
        </div>
        <div class='flex items-center justify-between'>
            <span>Arduino Connection:</span>
            <span class='px-2 py-1 rounded text-sm " . ($status['arduino_connection'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . "'>
                " . ($status['arduino_connection'] ? 'Connected' : 'Disconnected') . "
            </span>
        </div>
    </div>
</div>";

// Action buttons
echo "<div class='flex flex-wrap gap-3'>
    <button onclick='startServices()' class='bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200'>
        <i class='fa fa-play mr-2'></i>Start Services
    </button>
    <button onclick='stopServices()' class='bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-200'>
        <i class='fa fa-stop mr-2'></i>Stop Services
    </button>
    <button onclick='restartServices()' class='bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition-colors duration-200'>
        <i class='fa fa-refresh mr-2'></i>Restart Services
    </button>
    <a href='index.php' class='bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors duration-200'>
        <i class='fa fa-home mr-2'></i>Back to Dashboard
    </a>
</div>";

// Service logs
$logs = $manager->getServiceLogs(20);
if (!empty($logs)) {
    echo "<div class='mt-6'>
        <h3 class='font-semibold text-gray-800 mb-2'>Recent Service Logs:</h3>
        <div class='bg-gray-900 text-green-400 rounded-lg p-4 font-mono text-sm max-h-64 overflow-y-auto'>";
    foreach ($logs as $log) {
        echo htmlspecialchars($log) . "<br>";
    }
    echo "</div>
    </div>";
}

echo "</div>
        </div>
    </div>

    <script>
        function startServices() {
            fetch('rfid_service_manager.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=start_api'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Services started successfully!');
                    location.reload();
                } else {
                    alert('Failed to start services');
                }
            });
        }

        function stopServices() {
            if (confirm('Are you sure you want to stop RFID services?')) {
                fetch('rfid_service_manager.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=stop_api'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Services stopped successfully!');
                        location.reload();
                    } else {
                        alert('Failed to stop services');
                    }
                });
            }
        }

        function restartServices() {
            if (confirm('Are you sure you want to restart RFID services?')) {
                fetch('rfid_service_manager.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=restart_api'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Services restarted successfully!');
                        location.reload();
                    } else {
                        alert('Failed to restart services');
                    }
                });
            }
        }

        // Auto-refresh every 10 seconds
        setInterval(() => {
            location.reload();
        }, 10000);
    </script>
</body>
</html>";
?>
