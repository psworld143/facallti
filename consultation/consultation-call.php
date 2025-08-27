<?php
session_start();
require_once '../config/database.php';

// Set page title
$page_title = 'Consultation Call';

// Get teacher ID and session ID from URL parameter
$teacher_id = $_GET['teacher_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';
$mode = $_GET['mode'] ?? 'student';

if (empty($teacher_id)) {
    header('Location: student-screen.php');
    exit();
}

// Get teacher information
$teacher_query = "SELECT 
                    f.id,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position,
                    f.email,
                    f.bio,
                    f.image_url,
                    f.is_active
                   FROM faculty f 
                   WHERE f.id = ? AND f.is_active = 1";

$teacher_stmt = mysqli_prepare($conn, $teacher_query);
mysqli_stmt_bind_param($teacher_stmt, "i", $teacher_id);
mysqli_stmt_execute($teacher_stmt);
$teacher_result = mysqli_stmt_get_result($teacher_stmt);
$teacher = mysqli_fetch_assoc($teacher_result);

if (!$teacher) {
    header('Location: student-screen.php');
    exit();
}

// Get student information (if logged in)
$student_name = 'Student';
$student_id = null;

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    $student_id = $_SESSION['user_id'];
    $student_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
}

// Check if consultation request was accepted (for student mode)
if ($mode === 'student' && $session_id) {
    $check_query = "SELECT status FROM consultation_requests WHERE session_id = ? AND teacher_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "si", $session_id, $teacher_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $request_status = mysqli_fetch_assoc($check_result);
    
    if (!$request_status || $request_status['status'] !== 'accepted') {
        // Request not found or not accepted, redirect back to student screen
        header('Location: student-screen.php?error=request_not_accepted');
        exit();
    }
}

// Use provided session ID or generate new one
$consultation_session_id = $session_id ?: uniqid('consultation_', true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SEAIT</title>
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
    <style>
        .video-container {
            position: relative;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            overflow: hidden;
        }

        .video-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
        }

        .call-controls {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            padding: 1rem;
            z-index: 100;
        }

        .control-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .control-btn:hover {
            transform: scale(1.1);
        }

        .btn-end-call {
            background-color: #ef4444;
            color: white;
        }

        .btn-mute {
            background-color: #6b7280;
            color: white;
        }

        .btn-mute.active {
            background-color: #ef4444;
        }

        .btn-video {
            background-color: #6b7280;
            color: white;
        }

        .btn-video.active {
            background-color: #ef4444;
        }

        .btn-chat {
            background-color: #6b7280;
            color: white;
        }

        .btn-chat.active {
            background-color: #10b981;
        }

        .chat-panel {
            position: fixed;
            right: 0;
            top: 0;
            bottom: 0;
            width: 350px;
            background: white;
            border-left: 1px solid #e5e7eb;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 200;
        }

        .chat-panel.open {
            transform: translateX(0);
        }

        .chat-messages {
            height: calc(100vh - 200px);
            overflow-y: auto;
            padding: 1rem;
        }

        .message {
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            max-width: 80%;
        }

        .message.sent {
            background-color: #10b981;
            color: white;
            margin-left: auto;
        }

        .message.received {
            background-color: #f3f4f6;
            color: #374151;
        }

        .typing-indicator {
            display: none;
            padding: 0.5rem;
            color: #6b7280;
            font-style: italic;
        }

        .typing-indicator.show {
            display: block;
        }

        .call-status {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .connection-quality {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .loading {
            display: none;
        }

        .loading.show {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-900 h-screen overflow-hidden">
    <!-- Call Status -->
    <div class="call-status">
        <i class="fas fa-circle text-green-400 mr-2"></i>
        Connected to <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
    </div>

    <!-- Connection Quality -->
    <div class="connection-quality">
        <i class="fas fa-wifi mr-2"></i>
        Excellent
    </div>

    <!-- Main Video Area -->
    <div class="h-full flex">
        <!-- Video Container -->
        <div class="flex-1 p-4">
            <div class="video-container h-full">
                <div class="video-placeholder">
                    <i class="fas fa-user-circle text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold mb-2">
                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                    </h3>
                    <p class="text-lg opacity-75">
                        <?php echo htmlspecialchars($teacher['position']); ?>
                    </p>
                    <p class="text-sm opacity-60">
                        <?php echo htmlspecialchars($teacher['department']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Chat Panel -->
        <div id="chatPanel" class="chat-panel">
            <div class="bg-seait-orange text-white p-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold">Chat</h3>
                    <button id="closeChat" class="text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="message received">
                    <div class="font-semibold text-sm mb-1"><?php echo htmlspecialchars($teacher['first_name']); ?></div>
                    <div>Hello! How can I help you today?</div>
                </div>
            </div>
            
            <div class="typing-indicator" id="typingIndicator">
                <?php echo htmlspecialchars($teacher['first_name']); ?> is typing...
            </div>
            
            <div class="p-4 border-t">
                <div class="flex space-x-2">
                    <input type="text" id="messageInput" placeholder="Type your message..." 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <button id="sendMessage" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-seait-dark transition-colors">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Call Controls -->
    <div class="call-controls">
        <div class="flex items-center justify-center space-x-4">
            <!-- Mute Button -->
            <button id="muteBtn" class="control-btn btn-mute" title="Mute">
                <i class="fas fa-microphone"></i>
            </button>

            <!-- Video Button -->
            <button id="videoBtn" class="control-btn btn-video" title="Turn off video">
                <i class="fas fa-video"></i>
            </button>

            <!-- Chat Button -->
            <button id="chatBtn" class="control-btn btn-chat" title="Open chat">
                <i class="fas fa-comments"></i>
            </button>

            <!-- End Call Button -->
            <button id="endCallBtn" class="control-btn btn-end-call" title="End call">
                <i class="fas fa-phone-slash"></i>
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="loading fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-seait-orange"></div>
            <span class="text-gray-600">Ending call...</span>
        </div>
    </div>

    <!-- Teacher Information Modal -->
    <div id="teacherInfoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex items-center space-x-4 mb-4">
                                                <?php if ($teacher['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($teacher['image_url']); ?>"
                                         alt="Teacher" class="w-16 h-16 rounded-full object-cover">
                                <?php else: ?>
                    <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white text-xl"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h3 class="text-lg font-semibold text-seait-dark">
                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                    </h3>
                    <p class="text-gray-600"><?php echo htmlspecialchars($teacher['position']); ?></p>
                </div>
            </div>
            
            <div class="space-y-3">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-building text-gray-400"></i>
                    <span class="text-gray-700"><?php echo htmlspecialchars($teacher['department']); ?></span>
                </div>
                
                <?php if ($teacher['bio']): ?>
                <div class="flex items-center space-x-3">
                    <i class="fas fa-info-circle text-gray-400"></i>
                    <span class="text-gray-700"><?php echo htmlspecialchars(substr($teacher['bio'], 0, 50)) . (strlen($teacher['bio']) > 50 ? '...' : ''); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($teacher['email']): ?>
                <div class="flex items-center space-x-3">
                    <i class="fas fa-envelope text-gray-400"></i>
                    <span class="text-gray-700"><?php echo htmlspecialchars($teacher['email']); ?></span>
                </div>
                <?php endif; ?>
                

            </div>
            
            <div class="mt-6 flex space-x-3">
                <button id="closeTeacherInfo" class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
                    Close
                </button>
                <button id="scheduleFollowUp" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-seait-dark transition-colors">
                    Schedule Follow-up
                </button>
            </div>
        </div>
    </div>

    <script>
        // Call controls
        const muteBtn = document.getElementById('muteBtn');
        const videoBtn = document.getElementById('videoBtn');
        const chatBtn = document.getElementById('chatBtn');
        const endCallBtn = document.getElementById('endCallBtn');
        const chatPanel = document.getElementById('chatPanel');
        const closeChat = document.getElementById('closeChat');
        const messageInput = document.getElementById('messageInput');
        const sendMessage = document.getElementById('sendMessage');
        const chatMessages = document.getElementById('chatMessages');
        const typingIndicator = document.getElementById('typingIndicator');
        const loadingState = document.getElementById('loadingState');
        const teacherInfoModal = document.getElementById('teacherInfoModal');
        const closeTeacherInfo = document.getElementById('closeTeacherInfo');
        const scheduleFollowUp = document.getElementById('scheduleFollowUp');

        let isMuted = false;
        let isVideoOff = false;
        let isChatOpen = false;

        // Mute functionality
        muteBtn.addEventListener('click', function() {
            isMuted = !isMuted;
            this.classList.toggle('active');
            this.innerHTML = isMuted ? '<i class="fas fa-microphone-slash"></i>' : '<i class="fas fa-microphone"></i>';
            this.title = isMuted ? 'Unmute' : 'Mute';
            
            // Add notification
            showNotification(isMuted ? 'Microphone muted' : 'Microphone unmuted');
        });

        // Video functionality
        videoBtn.addEventListener('click', function() {
            isVideoOff = !isVideoOff;
            this.classList.toggle('active');
            this.innerHTML = isVideoOff ? '<i class="fas fa-video-slash"></i>' : '<i class="fas fa-video"></i>';
            this.title = isVideoOff ? 'Turn on video' : 'Turn off video';
            
            // Add notification
            showNotification(isVideoOff ? 'Video turned off' : 'Video turned on');
        });

        // Chat functionality
        chatBtn.addEventListener('click', function() {
            isChatOpen = !isChatOpen;
            chatPanel.classList.toggle('open');
            this.classList.toggle('active');
            this.title = isChatOpen ? 'Close chat' : 'Open chat';
        });

        closeChat.addEventListener('click', function() {
            isChatOpen = false;
            chatPanel.classList.remove('open');
            chatBtn.classList.remove('active');
            chatBtn.title = 'Open chat';
        });

        // Send message functionality
        function sendChatMessage() {
            const message = messageInput.value.trim();
            if (message) {
                const messageElement = document.createElement('div');
                messageElement.className = 'message sent';
                messageElement.innerHTML = `
                    <div class="font-semibold text-sm mb-1">You</div>
                    <div>${message}</div>
                `;
                chatMessages.appendChild(messageElement);
                messageInput.value = '';
                chatMessages.scrollTop = chatMessages.scrollHeight;

                // Simulate teacher response
                setTimeout(() => {
                    const teacherResponse = document.createElement('div');
                    teacherResponse.className = 'message received';
                    teacherResponse.innerHTML = `
                        <div class="font-semibold text-sm mb-1"><?php echo htmlspecialchars($teacher['first_name']); ?></div>
                        <div>Thank you for your message. I'll address this during our consultation.</div>
                    `;
                    chatMessages.appendChild(teacherResponse);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }, 2000);
            }
        }

        sendMessage.addEventListener('click', sendChatMessage);
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });

        // End call functionality
        endCallBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to end this consultation call?')) {
                loadingState.classList.add('show');
                
                // Update consultation request status to completed
                const formData = new FormData();
                formData.append('session_id', '<?php echo $consultation_session_id; ?>');
                formData.append('teacher_id', '<?php echo $teacher_id; ?>');
                formData.append('action', 'complete');
                
                fetch('update-consultation-status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Store consultation session data
                    const sessionData = {
                        teacher_id: '<?php echo $teacher_id; ?>',
                        teacher_name: '<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>',
                        student_name: '<?php echo htmlspecialchars($student_name); ?>',
                        session_id: '<?php echo $consultation_session_id; ?>',
                        start_time: new Date().toISOString(),
                        end_time: new Date().toISOString(),
                        duration: Math.floor(Math.random() * 30) + 5 // Random duration 5-35 minutes
                    };
                    
                    sessionStorage.setItem('lastConsultationSession', JSON.stringify(sessionData));
                    
                    // Redirect to consultation summary
                    setTimeout(() => {
                        window.location.href = 'consultation-summary.php?session_id=<?php echo $consultation_session_id; ?>';
                    }, 1000);
                })
                .catch(error => {
                    console.error('Error updating consultation status:', error);
                    // Still redirect even if update fails
                    setTimeout(() => {
                        window.location.href = 'consultation-summary.php?session_id=<?php echo $consultation_session_id; ?>';
                    }, 1000);
                });
            }
        });

        // Teacher info modal
        function showTeacherInfo() {
            teacherInfoModal.classList.remove('hidden');
        }

        closeTeacherInfo.addEventListener('click', function() {
            teacherInfoModal.classList.add('hidden');
        });

        scheduleFollowUp.addEventListener('click', function() {
            alert('Follow-up scheduling feature will be implemented soon.');
            teacherInfoModal.classList.add('hidden');
        });

        // Close modal when clicking outside
        teacherInfoModal.addEventListener('click', function(e) {
            if (e.target === teacherInfoModal) {
                teacherInfoModal.classList.add('hidden');
            }
        });

        // Show notification function - DISABLED (upper right notifications removed)
        function showNotification(message) {
            // Log to console instead of showing upper right notification
            console.log(`[NOTIFICATION] ${message}`);
            
            // Optionally, you can uncomment the code below to move notifications to bottom center
            /*
            const notification = document.createElement('div');
            notification.className = 'fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white px-4 py-2 rounded-lg z-50';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
            */
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'm':
                case 'M':
                    muteBtn.click();
                    break;
                case 'v':
                case 'V':
                    videoBtn.click();
                    break;
                case 'c':
                case 'C':
                    chatBtn.click();
                    break;
                case 'Escape':
                    if (isChatOpen) {
                        closeChat.click();
                    }
                    if (!teacherInfoModal.classList.contains('hidden')) {
                        closeTeacherInfo.click();
                    }
                    break;
            }
        });

        // Auto-scroll chat to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Show welcome message
        setTimeout(() => {
            showNotification('Welcome to your consultation session!');
        }, 1000);
    </script>
</body>
</html>
