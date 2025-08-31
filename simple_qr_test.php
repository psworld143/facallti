<?php
// Simple test page for QR code input
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple QR Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Simple QR Test</h1>
        
        <form id="qrForm">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">QR Code Input</label>
                <input type="text" id="qrInput" name="qrCode" placeholder="Enter QR code..." 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-center text-xl font-mono"
                       autocomplete="off" autofocus>
            </div>
        </form>
        
        <div id="result" class="mt-4 p-3 bg-gray-50 rounded-lg">
            <p class="text-sm text-gray-600">Enter a QR code and press Enter</p>
        </div>
        
        <div class="mt-4">
            <button onclick="testAPI()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Test API
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded');
            
            const qrForm = document.getElementById('qrForm');
            const qrInput = document.getElementById('qrInput');
            const result = document.getElementById('result');
            
            if (qrForm && qrInput) {
                console.log('Form and input found');
                
                // Form submit
                qrForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('Form submitted');
                    handleQRInput();
                });
                
                // Enter key
                qrInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        console.log('Enter pressed');
                        handleQRInput();
                    }
                });
                
                // Input event
                qrInput.addEventListener('input', function() {
                    console.log('Input:', this.value);
                });
                
                qrInput.focus();
            } else {
                console.error('Form or input not found');
            }
        });
        
        function handleQRInput() {
            const input = document.getElementById('qrInput');
            const result = document.getElementById('result');
            const value = input.value.trim();
            
            console.log('Handling input:', value);
            
            if (value) {
                result.innerHTML = `
                    <div class="text-green-600">
                        <strong>Input received:</strong> ${value}
                    </div>
                `;
                
                // Test API call
                testQRCode(value);
            } else {
                result.innerHTML = `
                    <div class="text-red-600">
                        <strong>No input provided</strong>
                    </div>
                `;
            }
        }
        
        function testQRCode(qrCode) {
            const result = document.getElementById('result');
            
            result.innerHTML += `
                <div class="mt-2 text-blue-600">
                    Testing QR code: ${qrCode}
                </div>
            `;
            
            const apiUrl = `api/teacher-availability-handler.php?action=verify_teacher&qr_code=${encodeURIComponent(qrCode)}`;
            
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    console.log('API response:', data);
                    result.innerHTML += `
                        <div class="mt-2 text-sm">
                            <strong>API Response:</strong> ${JSON.stringify(data)}
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('API error:', error);
                    result.innerHTML += `
                        <div class="mt-2 text-red-600">
                            <strong>API Error:</strong> ${error.message}
                        </div>
                    `;
                });
        }
        
        function testAPI() {
            testQRCode('2025-0002');
        }
    </script>
</body>
</html>
