<?php
session_start();
require_once '../config/database.php';

// Set page title
$page_title = 'Consultation Summary';

// Get session ID from URL parameter
$session_id = $_GET['session_id'] ?? '';

// Get session data from session storage (this would normally come from database)
$session_data = null;
if (isset($_SESSION['lastConsultationSession'])) {
    $session_data = json_decode($_SESSION['lastConsultationSession'], true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SEAIT</title>
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-12 w-auto">
                    <div class="ml-4">
                        <h1 class="text-xl font-bold text-seait-dark">Consultation Summary</h1>
                        <p class="text-sm text-gray-600">Session completed successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-seait-orange hover:text-seait-dark transition-colors">
                        <i class="fas fa-home mr-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Success Message -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-medium text-green-800">Consultation Completed Successfully!</h3>
                    <p class="text-green-700 mt-1">Your consultation session has ended. Thank you for using our platform.</p>
                </div>
            </div>
        </div>

        <!-- Session Details -->
        <div class="grid md:grid-cols-2 gap-8 mb-8">
            <!-- Session Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-seait-dark mb-4">
                    <i class="fas fa-info-circle mr-2 text-seait-orange"></i>
                    Session Information
                </h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Session ID:</span>
                        <span class="font-medium text-seait-dark"><?php echo htmlspecialchars($session_id); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Date:</span>
                        <span class="font-medium text-seait-dark"><?php echo date('F j, Y'); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Time:</span>
                        <span class="font-medium text-seait-dark"><?php echo date('g:i A'); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Duration:</span>
                        <span class="font-medium text-seait-dark"><?php echo isset($session_data['duration']) ? $session_data['duration'] . ' minutes' : 'N/A'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Teacher Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-seait-dark mb-4">
                    <i class="fas fa-user-tie mr-2 text-seait-orange"></i>
                    Teacher Information
                </h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Name:</span>
                        <span class="font-medium text-seait-dark">
                            <?php echo isset($session_data['teacher_name']) ? htmlspecialchars($session_data['teacher_name']) : 'N/A'; ?>
                        </span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Department:</span>
                        <span class="font-medium text-seait-dark">
                            <?php echo isset($session_data['teacher_dept']) ? htmlspecialchars($session_data['teacher_dept']) : 'N/A'; ?>
                        </span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Student:</span>
                        <span class="font-medium text-seait-dark">
                            <?php echo isset($session_data['student_name']) ? htmlspecialchars($session_data['student_name']) : 'Student'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-seait-dark mb-4">
                <i class="fas fa-arrow-right mr-2 text-seait-orange"></i>
                Next Steps
            </h3>
            
            <div class="grid md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-seait-dark mb-2">Schedule Follow-up</h4>
                    <p class="text-gray-600 text-sm">Book another consultation session if needed</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-file-alt text-green-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-seait-dark mb-2">Review Notes</h4>
                    <p class="text-gray-600 text-sm">Check your consultation notes and action items</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-star text-purple-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-seait-dark mb-2">Rate Session</h4>
                    <p class="text-gray-600 text-sm">Provide feedback on your consultation experience</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="student-consultation.php" class="bg-seait-orange hover:bg-seait-dark text-white px-6 py-3 rounded-lg font-medium transition-colors text-center">
                <i class="fas fa-video mr-2"></i>
                Start New Consultation
            </a>
            
            <a href="../index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-medium transition-colors text-center">
                <i class="fas fa-home mr-2"></i>
                Return to Home
            </a>
            
            <button onclick="downloadSummary()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                <i class="fas fa-download mr-2"></i>
                Download Summary
            </button>
        </div>

        <!-- Feedback Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h3 class="text-lg font-semibold text-seait-dark mb-4">
                <i class="fas fa-comment-alt mr-2 text-seait-orange"></i>
                Rate Your Experience
            </h3>
            
            <div class="flex items-center space-x-4 mb-4">
                <span class="text-gray-600">How would you rate this consultation?</span>
                <div class="flex space-x-2">
                    <button class="rating-star text-2xl text-gray-300 hover:text-yellow-400" data-rating="1">★</button>
                    <button class="rating-star text-2xl text-gray-300 hover:text-yellow-400" data-rating="2">★</button>
                    <button class="rating-star text-2xl text-gray-300 hover:text-yellow-400" data-rating="3">★</button>
                    <button class="rating-star text-2xl text-gray-300 hover:text-yellow-400" data-rating="4">★</button>
                    <button class="rating-star text-2xl text-gray-300 hover:text-yellow-400" data-rating="5">★</button>
                </div>
            </div>
            
            <textarea id="feedbackText" placeholder="Share your feedback about the consultation session..." 
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange" rows="3"></textarea>
            
            <div class="mt-4">
                <button onclick="submitFeedback()" class="bg-seait-orange hover:bg-seait-dark text-white px-4 py-2 rounded-lg transition-colors">
                    Submit Feedback
                </button>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-seait-dark text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> SEAIT. All rights reserved.</p>
                <p class="text-gray-400 text-sm mt-2">Consultation Portal - Thank you for using our services</p>
            </div>
        </div>
    </footer>

    <script>
        let selectedRating = 0;

        // Rating functionality
        const ratingStars = document.querySelectorAll('.rating-star');
        ratingStars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                selectedRating = rating;
                
                // Update star colors
                ratingStars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('text-gray-300');
                        s.classList.add('text-yellow-400');
                    } else {
                        s.classList.remove('text-yellow-400');
                        s.classList.add('text-gray-300');
                    }
                });
            });
        });

        // Submit feedback
        function submitFeedback() {
            const feedback = document.getElementById('feedbackText').value.trim();
            
            if (selectedRating === 0) {
                alert('Please select a rating before submitting feedback.');
                return;
            }
            
            // Here you would normally send the feedback to the server
            console.log('Rating:', selectedRating);
            console.log('Feedback:', feedback);
            
            alert('Thank you for your feedback! Your response has been recorded.');
            
            // Clear form
            selectedRating = 0;
            ratingStars.forEach(star => {
                star.classList.remove('text-yellow-400');
                star.classList.add('text-gray-300');
            });
            document.getElementById('feedbackText').value = '';
        }

        // Download summary
        function downloadSummary() {
            const sessionData = <?php echo json_encode($session_data ?? []); ?>;
            
            // Create summary content
            let summary = 'SEAIT Consultation Summary\n';
            summary += '========================\n\n';
            summary += `Session ID: ${sessionData.session_id || 'N/A'}\n`;
            summary += `Date: ${new Date().toLocaleDateString()}\n`;
            summary += `Time: ${new Date().toLocaleTimeString()}\n`;
            summary += `Duration: ${sessionData.duration || 'N/A'} minutes\n\n`;
            summary += `Teacher: ${sessionData.teacher_name || 'N/A'}\n`;
            summary += `Department: ${sessionData.teacher_dept || 'N/A'}\n`;
            summary += `Student: ${sessionData.student_name || 'Student'}\n\n`;
            summary += 'Thank you for using our consultation services!';
            
            // Create and download file
            const blob = new Blob([summary], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `consultation-summary-${sessionData.session_id || 'session'}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Auto-clear session data after 5 minutes
        setTimeout(() => {
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.removeItem('lastConsultationSession');
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>
