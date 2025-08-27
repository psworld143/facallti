<?php
require_once '../../includes/session-config.php';
session_start();
require_once '../../includes/config.php';

// Set page title
$page_title = 'Session Test';

// Include unified navigation
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

<main class="ml-64 mt-16 p-6 flex-1">
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Session Test</h2>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Current Session Status</h3>
                <div class="bg-gray-100 p-4 rounded-md">
                    <pre><?php print_r($_SESSION); ?></pre>
                </div>
            </div>
            
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Test Service Requests API</h3>
                <button onclick="testAPI()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Test API with Session
                </button>
                <div id="api-result" class="mt-4 p-4 bg-gray-100 rounded-md hidden">
                    <pre id="api-data"></pre>
                </div>
            </div>
            
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Session Cookie Info</h3>
                <div class="bg-gray-100 p-4 rounded-md">
                    <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
                    <p><strong>Session Name:</strong> <?php echo session_name(); ?></p>
                    <p><strong>Session Status:</strong> <?php echo session_status(); ?></p>
                    <p><strong>User Logged In:</strong> <?php echo isset($_SESSION['user_id']) ? 'YES' : 'NO'; ?></p>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
                        <p><strong>User Name:</strong> <?php echo $_SESSION['user_name']; ?></p>
                        <p><strong>User Role:</strong> <?php echo $_SESSION['user_role']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function testAPI() {
    fetch('../../api/get-service-requests.php', {
        credentials: 'same-origin'
    })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                document.getElementById('api-data').textContent = JSON.stringify(data, null, 2);
                document.getElementById('api-result').classList.remove('hidden');
            } catch (e) {
                document.getElementById('api-data').textContent = 'Error parsing JSON: ' + e.message + '\n\nRaw response: ' + text;
                document.getElementById('api-result').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('api-data').textContent = 'Fetch error: ' + error.message;
            document.getElementById('api-result').classList.remove('hidden');
        });
}
</script>

<?php include '../../includes/footer.php'; ?>
