<?php
// Simple test page for training API endpoints
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training API Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Training API Test</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Test Get Training Scenarios -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Get Training Scenarios</h2>
                <button onclick="testGetScenarios()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Test API
                </button>
                <div id="scenarios-result" class="mt-4 p-4 bg-gray-50 rounded text-sm"></div>
            </div>
            
            <!-- Test Get Scenario Details -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Get Scenario Details</h2>
                <button onclick="testGetScenarioDetails()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Test API
                </button>
                <div id="scenario-details-result" class="mt-4 p-4 bg-gray-50 rounded text-sm"></div>
            </div>
            
            <!-- Test Submit Scenario -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Submit Scenario</h2>
                <button onclick="testSubmitScenario()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    Test API
                </button>
                <div id="submit-scenario-result" class="mt-4 p-4 bg-gray-50 rounded text-sm"></div>
            </div>
            
            <!-- Test Customer Service -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Customer Service</h2>
                <button onclick="testCustomerService()" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    Test API
                </button>
                <div id="customer-service-result" class="mt-4 p-4 bg-gray-50 rounded text-sm"></div>
            </div>
        </div>
        
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">All Results</h2>
            <div id="all-results" class="space-y-4"></div>
        </div>
    </div>

    <script>
        function logResult(testName, result) {
            const allResults = document.getElementById('all-results');
            const timestamp = new Date().toLocaleTimeString();
            const status = result.success ? '✅ SUCCESS' : '❌ ERROR';
            const div = document.createElement('div');
            div.className = 'p-3 border rounded';
            div.innerHTML = `
                <div class="font-semibold">${timestamp} - ${testName} - ${status}</div>
                <pre class="text-xs mt-2 overflow-auto">${JSON.stringify(result, null, 2)}</pre>
            `;
            allResults.appendChild(div);
        }

        async function testGetScenarios() {
            const resultDiv = document.getElementById('scenarios-result');
            resultDiv.textContent = 'Testing...';
            
            try {
                const response = await fetch('api/get-training-scenarios.php');
                const data = await response.json();
                resultDiv.textContent = JSON.stringify(data, null, 2);
                logResult('Get Training Scenarios', data);
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
                logResult('Get Training Scenarios', { success: false, error: error.message });
            }
        }

        async function testGetScenarioDetails() {
            const resultDiv = document.getElementById('scenario-details-result');
            resultDiv.textContent = 'Testing...';
            
            try {
                const response = await fetch('api/get-scenario-details.php?id=front_desk_basic');
                const data = await response.json();
                resultDiv.textContent = JSON.stringify(data, null, 2);
                logResult('Get Scenario Details', data);
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
                logResult('Get Scenario Details', { success: false, error: error.message });
            }
        }

        async function testSubmitScenario() {
            const resultDiv = document.getElementById('submit-scenario-result');
            resultDiv.textContent = 'Testing...';
            
            try {
                const response = await fetch('api/submit-scenario.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ q0: 'a', q1: 'b' })
                });
                const data = await response.json();
                resultDiv.textContent = JSON.stringify(data, null, 2);
                logResult('Submit Scenario', data);
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
                logResult('Submit Scenario', { success: false, error: error.message });
            }
        }

        async function testCustomerService() {
            const resultDiv = document.getElementById('customer-service-result');
            resultDiv.textContent = 'Testing...';
            
            try {
                const response = await fetch('api/get-customer-service-details.php?id=customer_service');
                const data = await response.json();
                resultDiv.textContent = JSON.stringify(data, null, 2);
                logResult('Customer Service Details', data);
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
                logResult('Customer Service Details', { success: false, error: error.message });
            }
        }

        // Auto-run all tests on page load
        window.addEventListener('load', async () => {
            await testGetScenarios();
            await testGetScenarioDetails();
            await testSubmitScenario();
            await testCustomerService();
        });
    </script>
</body>
</html>
