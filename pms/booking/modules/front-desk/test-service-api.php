<?php
session_start();
require_once '../../includes/config.php';

// Set page title
$page_title = 'Service API Test';

// Include unified navigation
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

<main class="ml-64 mt-16 p-6 flex-1">
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Service API Test</h2>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Test Service Requests API</h3>
                <button onclick="testServiceRequests()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Test API
                </button>
                <div id="service-requests-result" class="mt-4 p-4 bg-gray-100 rounded-md hidden">
                    <pre id="service-requests-data"></pre>
                </div>
            </div>
            
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Test Notifications API</h3>
                <button onclick="testNotifications()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Test Notifications
                </button>
                <div id="notifications-result" class="mt-4 p-4 bg-gray-100 rounded-md hidden">
                    <pre id="notifications-data"></pre>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function testServiceRequests() {
    fetch('../../api/get-service-requests.php')
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                document.getElementById('service-requests-data').textContent = JSON.stringify(data, null, 2);
                document.getElementById('service-requests-result').classList.remove('hidden');
            } catch (e) {
                document.getElementById('service-requests-data').textContent = 'Error parsing JSON: ' + e.message + '\n\nRaw response: ' + text;
                document.getElementById('service-requests-result').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('service-requests-data').textContent = 'Fetch error: ' + error.message;
            document.getElementById('service-requests-result').classList.remove('hidden');
        });
}

function testNotifications() {
    const currentPath = window.location.pathname;
    const apiPath = currentPath.includes('/modules/') ? '../../api/get-notifications.php' : '../api/get-notifications.php';
    
    fetch(apiPath)
        .then(response => {
            console.log('Notifications response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Notifications raw response:', text);
            try {
                const data = JSON.parse(text);
                document.getElementById('notifications-data').textContent = JSON.stringify(data, null, 2);
                document.getElementById('notifications-result').classList.remove('hidden');
            } catch (e) {
                document.getElementById('notifications-data').textContent = 'Error parsing JSON: ' + e.message + '\n\nRaw response: ' + text;
                document.getElementById('notifications-result').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Notifications fetch error:', error);
            document.getElementById('notifications-data').textContent = 'Fetch error: ' + error.message;
            document.getElementById('notifications-result').classList.remove('hidden');
        });
}
</script>

<?php include '../../includes/footer.php'; ?>
