<!-- Floating Action Button for Inquiries -->
<div id="inquiry-fab" class="fixed bottom-6 right-6 z-50">
    <button id="fab-button" class="bg-seait-orange text-white w-14 h-14 rounded-full shadow-lg hover:shadow-xl transform hover:scale-110 transition-all duration-300 flex items-center justify-center">
        <i class="fas fa-comments text-xl"></i>
    </button>
</div>

<!-- Inquiry Modal -->
<div id="inquiry-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[80vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-seait-orange text-white p-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Ask a Question</h3>
                <button id="close-modal" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <div id="chat-container" class="space-y-4 mb-4 max-h-96 overflow-y-auto">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-robot text-white text-sm"></i>
                        </div>
                        <div class="bg-gray-100 rounded-lg p-3 max-w-xs">
                            <p class="text-sm text-gray-800">Hello! I'm here to help. Ask me anything about SEAIT, our programs, admission process, or any other questions you might have.</p>
                        </div>
                    </div>
                </div>

                <!-- Input Form -->
                <form id="inquiry-form" class="flex space-x-2">
                    <input type="text" id="inquiry-input" placeholder="Type your question here..."
                           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Floating Action Button Inquiry System
document.addEventListener('DOMContentLoaded', function() {
    const fabButton = document.getElementById('fab-button');
    const inquiryModal = document.getElementById('inquiry-modal');
    const closeModal = document.getElementById('close-modal');
    const inquiryForm = document.getElementById('inquiry-form');
    const inquiryInput = document.getElementById('inquiry-input');
    const chatContainer = document.getElementById('chat-container');

    // Open modal
    fabButton.addEventListener('click', function() {
        inquiryModal.classList.remove('hidden');
        inquiryInput.focus();
    });

    // Close modal
    closeModal.addEventListener('click', function() {
        inquiryModal.classList.add('hidden');
    });

    // Close modal when clicking outside
    inquiryModal.addEventListener('click', function(e) {
        if (e.target === inquiryModal) {
            inquiryModal.classList.add('hidden');
        }
    });

    // Handle form submission
    inquiryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const question = inquiryInput.value.trim();
        if (!question) return;

        // Add user message
        addMessage(question, 'user');
        inquiryInput.value = '';

        // Get automatic response
        getAutomaticResponse(question);
    });

    function addMessage(message, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex items-start space-x-3';

        if (sender === 'user') {
            messageDiv.innerHTML = `
                <div class="flex-1"></div>
                <div class="bg-seait-orange text-white rounded-lg p-3 max-w-xs">
                    <p class="text-sm">${message}</p>
                </div>
                <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-robot text-white text-sm"></i>
                </div>
                <div class="bg-gray-100 rounded-lg p-3 max-w-xs">
                    <p class="text-sm text-gray-800">${message}</p>
                </div>
            `;
        }

        chatContainer.appendChild(messageDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.id = 'typing-indicator';
        typingDiv.className = 'flex items-start space-x-3';
        typingDiv.innerHTML = `
            <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-white text-sm"></i>
            </div>
            <div class="bg-gray-100 rounded-lg p-3">
                <div class="flex space-x-1">
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                </div>
            </div>
        `;
        chatContainer.appendChild(typingDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function hideTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }

    function getAutomaticResponse(question) {
        // Call API endpoint
        fetch('api/inquiry-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                question: question
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addMessage(data.response, 'bot');

                // Show typing indicator after bot's reply to simulate thinking
                setTimeout(() => {
                    showTypingIndicator();

                    // Hide typing indicator after a short delay
                    setTimeout(() => {
                        hideTypingIndicator();
                    }, 1500); // Show typing for 1.5 seconds
                }, 1000); // Wait 1 second after bot's reply
            } else {
                addMessage("I'm sorry, I couldn't process your question right now. Please try again or contact us directly.", 'bot');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Fallback to local keyword matching if API fails
            const response = getResponseByKeywords(question);
            addMessage(response, 'bot');

            // Show typing indicator after fallback response
            setTimeout(() => {
                showTypingIndicator();
                setTimeout(() => {
                    hideTypingIndicator();
                }, 1500);
            }, 1000);
        });
    }

    function getResponseByKeywords(question) {
        const lowerQuestion = question.toLowerCase();

        // Admission related questions
        if (lowerQuestion.includes('admission') || lowerQuestion.includes('apply') || lowerQuestion.includes('enroll')) {
            return "For admission inquiries, you can visit our Admission Process section on this page, or contact our admission office. We offer various programs including undergraduate and graduate degrees. You can also start your application through our pre-registration form.";
        }

        // Program related questions
        if (lowerQuestion.includes('program') || lowerQuestion.includes('course') || lowerQuestion.includes('degree')) {
            return "SEAIT offers various academic programs across different colleges. You can explore our Academic Programs section to see all available courses. Each program has detailed information about curriculum, requirements, and career opportunities.";
        }

        // Contact related questions
        if (lowerQuestion.includes('contact') || lowerQuestion.includes('phone') || lowerQuestion.includes('email')) {
            return "You can find our contact information in the Contact Us section. We have different departments with specific contact details. For general inquiries, you can reach us through the contact form or call our main office.";
        }

        // Location related questions
        if (lowerQuestion.includes('location') || lowerQuestion.includes('address') || lowerQuestion.includes('where')) {
            return "SEAIT is located in [City, Province]. You can find our exact address and directions in the Contact Us section. We also have virtual tours available for prospective students.";
        }

        // Fee related questions
        if (lowerQuestion.includes('fee') || lowerQuestion.includes('tuition') || lowerQuestion.includes('cost') || lowerQuestion.includes('price')) {
            return "Tuition fees vary by program and level. For detailed information about fees and payment options, please contact our finance office or check our admission guide. We also offer scholarships and financial aid programs.";
        }

        // Schedule related questions
        if (lowerQuestion.includes('schedule') || lowerQuestion.includes('time') || lowerQuestion.includes('when')) {
            return "Class schedules vary by program and semester. You can check our academic calendar for important dates. For specific class schedules, please contact your department or check the student portal.";
        }

        // General questions
        if (lowerQuestion.includes('hello') || lowerQuestion.includes('hi') || lowerQuestion.includes('help')) {
            return "Hello! I'm here to help you with any questions about SEAIT. You can ask me about our programs, admission process, contact information, or any other general inquiries.";
        }

        // Default response
        return "Thank you for your question! For specific inquiries, I recommend contacting our relevant department directly. You can find contact information in the Contact Us section, or visit our main office during business hours.";
    }
});
</script>