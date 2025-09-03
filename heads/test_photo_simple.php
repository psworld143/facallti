<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Photo Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-xl font-bold text-gray-800 mb-4">Simple Photo Test</h1>
        
        <form id="testForm" method="POST" action="add-faculty.php" enctype="multipart/form-data">
            <input type="hidden" name="department" value="Computer Science">
            <input type="hidden" name="qrcode" value="TEST-1234">
            <input type="hidden" name="first_name" value="Test">
            <input type="hidden" name="last_name" value="User">
            <input type="hidden" name="middle_name" value="Debug">
            
            <!-- Photo Section -->
            <div class="mb-4">
                <h3 class="font-semibold mb-2">Photo Capture</h3>
                
                <!-- Photo Preview -->
                <div id="photoPreview" class="w-24 h-24 rounded-full bg-gray-200 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden mb-3">
                    <span class="text-gray-400 text-sm">No Photo</span>
                </div>
                
                <!-- Camera View -->
                <div id="cameraView" class="hidden mb-3">
                    <video id="cameraVideo" class="w-full h-32 bg-gray-900 rounded-lg" autoplay playsinline></video>
                    <canvas id="cameraCanvas" class="hidden"></canvas>
                    
                    <div class="flex justify-center space-x-2 mt-2">
                        <button type="button" id="cancelCamera" class="px-2 py-1 text-gray-600 bg-gray-100 rounded text-xs hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="button" id="capturePhoto" class="px-2 py-1 bg-green-500 text-white rounded text-xs hover:bg-green-600">
                            Capture
                        </button>
                    </div>
                </div>
                
                <!-- Controls -->
                <div class="flex gap-2 mb-3">
                    <button type="button" id="takePhoto" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                        Take Photo
                    </button>
                    <button type="button" id="debugPhoto" class="bg-purple-500 text-white px-3 py-1 rounded text-sm hover:bg-purple-600">
                        Debug
                    </button>
                </div>
                
                <!-- Hidden Inputs -->
                <input type="hidden" id="captured_photo" name="captured_photo">
                <input type="file" id="faculty_photo" name="faculty_photo" accept="image/*" class="hidden">
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                Submit Test
            </button>
        </form>
        
        <!-- Debug Output -->
        <div class="mt-4 p-3 bg-gray-100 rounded-lg">
            <h4 class="font-semibold mb-2">Debug Output:</h4>
            <div id="debugOutput" class="text-xs text-gray-700 font-mono bg-white p-2 rounded border max-h-32 overflow-y-auto"></div>
        </div>
    </div>

    <script>
        let currentStream = null;
        
        function log(message) {
            const output = document.getElementById('debugOutput');
            const timestamp = new Date().toLocaleTimeString();
            output.innerHTML += `[${timestamp}] ${message}<br>`;
            output.scrollTop = output.scrollHeight;
            console.log(message);
        }
        
        // Take Photo Button
        document.getElementById('takePhoto').addEventListener('click', function() {
            log('Take Photo clicked');
            document.getElementById('cameraView').classList.remove('hidden');
            startCamera();
        });
        
        // Cancel Camera
        document.getElementById('cancelCamera').addEventListener('click', function() {
            log('Cancel camera clicked');
            stopCamera();
            document.getElementById('cameraView').classList.add('hidden');
        });
        
        // Capture Photo
        document.getElementById('capturePhoto').addEventListener('click', function() {
            log('Capture clicked');
            capturePhoto();
        });
        
        // Debug Photo
        document.getElementById('debugPhoto').addEventListener('click', function() {
            const capturedPhoto = document.getElementById('captured_photo').value;
            log('=== DEBUG PHOTO ===');
            log(`Value length: ${capturedPhoto.length}`);
            log(`Starts with: ${capturedPhoto.substring(0, 50)}`);
            log(`Contains data:image: ${capturedPhoto.includes('data:image')}`);
            log('=== END DEBUG ===');
        });
        
        // Start Camera
        function startCamera() {
            log('Starting camera...');
            const video = document.getElementById('cameraVideo');
            
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ video: true })
                    .then(function(stream) {
                        currentStream = stream;
                        video.srcObject = stream;
                        video.play();
                        log('Camera started successfully');
                    })
                    .catch(function(error) {
                        log(`Camera error: ${error.message}`);
                    });
            } else {
                log('Camera not supported');
            }
        }
        
        // Stop Camera
        function stopCamera() {
            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
                currentStream = null;
                log('Camera stopped');
            }
        }
        
        // Capture Photo
        function capturePhoto() {
            log('Capturing photo...');
            const video = document.getElementById('cameraVideo');
            const canvas = document.getElementById('cameraCanvas');
            const preview = document.getElementById('photoPreview');
            const capturedPhotoInput = document.getElementById('captured_photo');
            
            // Set canvas dimensions
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            log(`Canvas dimensions: ${canvas.width}x${canvas.height}`);
            
            // Draw video frame to canvas
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            
            // Convert to base64
            const photoData = canvas.toDataURL('image/jpeg', 0.8);
            log(`Photo captured, data length: ${photoData.length}`);
            log(`Data starts with: ${photoData.substring(0, 50)}`);
            
            // Store in hidden input
            capturedPhotoInput.value = photoData;
            log('Photo stored in captured_photo input');
            
            // Show preview
            preview.innerHTML = `<img src="${photoData}" class="w-full h-full object-cover rounded-full">`;
            
            // Hide camera view
            document.getElementById('cameraView').classList.add('hidden');
            stopCamera();
            
            log('Photo capture complete');
        }
        
        // Form Submission
        document.getElementById('testForm').addEventListener('submit', function(e) {
            const capturedPhoto = document.getElementById('captured_photo').value;
            
            log('=== FORM SUBMISSION ===');
            log(`QR Code: TEST-1234`);
            log(`First Name: Test`);
            log(`Last Name: User`);
            log(`Captured Photo length: ${capturedPhoto.length}`);
            log(`Captured Photo starts with: ${capturedPhoto.substring(0, 50)}`);
            log('=== END FORM SUBMISSION ===');
            
            if (capturedPhoto.length < 100) {
                log('WARNING: Captured photo data seems too short!');
                e.preventDefault();
                alert('Photo data seems incomplete. Please try capturing again.');
                return false;
            }
            
            log('Form submitted successfully');
            return true;
        });
        
        log('Test page loaded successfully');
    </script>
</body>
</html>
