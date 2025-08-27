<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Click Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .teacher-card {
            transition: all 0.3s ease;
            cursor: pointer;
            height: 200px;
        }
        .teacher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 p-8">
    <h1 class="text-2xl font-bold mb-6">Click Test</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="teacher-card bg-white rounded-lg shadow-md p-6 border border-gray-200" 
             data-teacher-id="1"
             data-teacher-name="Dr. Maria Santos"
             data-teacher-dept="College of Engineering">
            <h3 class="text-lg font-semibold mb-2">Dr. Maria Santos</h3>
            <p class="text-gray-600">College of Engineering</p>
            <p class="text-sm text-gray-500 mt-2">Click to test</p>
        </div>
        
        <div class="teacher-card bg-white rounded-lg shadow-md p-6 border border-gray-200" 
             data-teacher-id="2"
             data-teacher-name="Michael Paul Sebando"
             data-teacher-dept="College of Information and Communication Technology">
            <h3 class="text-lg font-semibold mb-2">Michael Paul Sebando</h3>
            <p class="text-gray-600">College of Information and Communication Technology</p>
            <p class="text-sm text-gray-500 mt-2">Click to test</p>
        </div>
    </div>
    
    <div id="debugOutput" class="mt-6 p-4 bg-gray-100 rounded-lg">
        <h3 class="font-semibold mb-2">Debug Output:</h3>
        <div id="debugContent"></div>
    </div>
    
    <script>
        function log(message) {
            const debugContent = document.getElementById('debugContent');
            const timestamp = new Date().toLocaleTimeString();
            debugContent.innerHTML += `<div>[${timestamp}] ${message}</div>`;
            console.log(message);
        }
        
        // Test basic click functionality
        document.addEventListener('DOMContentLoaded', function() {
            log('DOM loaded');
            
            const teacherCards = document.querySelectorAll('.teacher-card');
            log(`Found ${teacherCards.length} teacher cards`);
            
            teacherCards.forEach((card, index) => {
                log(`Setting up click listener for card ${index + 1}`);
                
                // Test basic click
                card.addEventListener('click', function(e) {
                    log('Card clicked!');
                    log(`Event target: ${e.target.tagName}`);
                    log(`Event currentTarget: ${e.currentTarget.tagName}`);
                    
                    const teacherId = this.getAttribute('data-teacher-id');
                    const teacherName = this.getAttribute('data-teacher-name');
                    const teacherDept = this.getAttribute('data-teacher-dept');
                    
                    log(`Teacher data: ID=${teacherId}, Name=${teacherName}, Dept=${teacherDept}`);
                    
                    // Test API call
                    const formData = new FormData();
                    formData.append('teacher_id', teacherId);
                    formData.append('student_name', 'Test Student');
                    formData.append('student_dept', teacherDept);
                    formData.append('student_id', '');
                    
                    log('Sending API request...');
                    
                    fetch('submit-consultation-request.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        log(`Response status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        log(`API response: ${JSON.stringify(data)}`);
                        if (data.success) {
                            alert(`Success! Session ID: ${data.session_id}`);
                        } else {
                            alert(`Error: ${data.error}`);
                        }
                    })
                    .catch(error => {
                        log(`API error: ${error.message}`);
                        alert(`Network error: ${error.message}`);
                    });
                });
                
                // Test mouse events
                card.addEventListener('mouseenter', function() {
                    log(`Mouse entered card ${index + 1}`);
                });
                
                card.addEventListener('mouseleave', function() {
                    log(`Mouse left card ${index + 1}`);
                });
            });
        });
        
        // Test if there are any JavaScript errors
        window.addEventListener('error', function(e) {
            log(`JavaScript error: ${e.message} at ${e.filename}:${e.lineno}`);
        });
        
        log('Script loaded');
    </script>
</body>
</html>
