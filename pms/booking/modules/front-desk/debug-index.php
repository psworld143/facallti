<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

// Debug: Test database connection
try {
    $test_stmt = $pdo->query("SELECT 1");
    $db_connected = true;
} catch (PDOException $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

// Debug: Test getFrontDeskStats function
if ($db_connected) {
    try {
        $stats = getFrontDeskStats();
        $stats_working = true;
    } catch (Exception $e) {
        $stats_working = false;
        $stats_error = $e->getMessage();
    }
}

// Debug: Test API endpoints
$api_test_results = [];

// Test get-recent-reservations.php
$api_test_results['recent_reservations'] = 'Not tested';

// Test get-today-schedule.php
$api_test_results['today_schedule'] = 'Not tested';

// Set page title
$page_title = 'Front Desk Dashboard - Debug';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Debug Information -->
        <div class="bg-white p-6 mb-6 shadow-md">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Debug Information</h1>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">Database Connection</h3>
                    <p class="text-sm">
                        Status: 
                        <?php if ($db_connected): ?>
                            <span class="text-green-600 font-medium">✓ Connected</span>
                        <?php else: ?>
                            <span class="text-red-600 font-medium">✗ Failed</span>
                            <br>Error: <?php echo htmlspecialchars($db_error); ?>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">Front Desk Stats Function</h3>
                    <p class="text-sm">
                        Status: 
                        <?php if (isset($stats_working) && $stats_working): ?>
                            <span class="text-green-600 font-medium">✓ Working</span>
                            <br>Stats: <?php echo json_encode($stats); ?>
                        <?php else: ?>
                            <span class="text-red-600 font-medium">✗ Failed</span>
                            <?php if (isset($stats_error)): ?>
                                <br>Error: <?php echo htmlspecialchars($stats_error); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">API Endpoints</h3>
                    <p class="text-sm">
                        get-recent-reservations.php: 
                        <span id="api-test-1" class="text-yellow-600">Testing...</span>
                    </p>
                    <p class="text-sm">
                        get-today-schedule.php: 
                        <span id="api-test-2" class="text-yellow-600">Testing...</span>
                    </p>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">Session Information</h3>
                    <p class="text-sm">
                        User ID: <?php echo $_SESSION['user_id'] ?? 'Not set'; ?><br>
                        User Role: <?php echo $_SESSION['user_role'] ?? 'Not set'; ?><br>
                        User Name: <?php echo $_SESSION['user_name'] ?? 'Not set'; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Test API Calls -->
        <div class="bg-white p-6 mb-6 shadow-md">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">API Test Results</h2>
            <div id="api-results" class="space-y-4">
                <!-- API test results will be displayed here -->
            </div>
        </div>

        <!-- Console Log -->
        <div class="bg-white p-6 shadow-md">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Console Log</h2>
            <div id="console-log" class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm h-64 overflow-y-auto">
                <!-- Console messages will be displayed here -->
            </div>
        </div>
    </div>

    <script>
        // Console logging function
        function logToConsole(message, type = 'info') {
            const consoleDiv = document.getElementById('console-log');
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? 'text-red-400' : type === 'success' ? 'text-green-400' : 'text-blue-400';
            consoleDiv.innerHTML += `<div class="${color}">[${timestamp}] ${message}</div>`;
            consoleDiv.scrollTop = consoleDiv.scrollHeight;
        }

        // Test API endpoints
        async function testAPI(url, testId) {
            try {
                logToConsole(`Testing ${url}...`);
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById(testId).innerHTML = '<span class="text-green-600">✓ Working</span>';
                    logToConsole(`${url} - Success: ${JSON.stringify(data)}`, 'success');
                } else {
                    document.getElementById(testId).innerHTML = '<span class="text-red-600">✗ Failed</span>';
                    logToConsole(`${url} - Error: ${data.message}`, 'error');
                }
            } catch (error) {
                document.getElementById(testId).innerHTML = '<span class="text-red-600">✗ Failed</span>';
                logToConsole(`${url} - Exception: ${error.message}`, 'error');
            }
        }

        // Run tests when page loads
        document.addEventListener('DOMContentLoaded', function() {
            logToConsole('Debug page loaded');
            
            // Test API endpoints
            testAPI('../../api/get-recent-reservations.php', 'api-test-1');
            testAPI('../../api/get-today-schedule.php', 'api-test-2');
            
            // Test JavaScript errors
            window.addEventListener('error', function(e) {
                logToConsole(`JavaScript Error: ${e.message}`, 'error');
            });
        });
    </script>
</body>
</html>
