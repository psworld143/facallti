// Training Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeTrainingDashboard();
    loadScenarios();
});

// Trivia refresh function
function refreshTrivia() {
    const triviaFacts = [
        "The world's largest hotel is the First World Hotel in Malaysia with 7,351 rooms.",
        "The Burj Al Arab in Dubai has a helipad on its roof, 210 meters above the ground.",
        "The oldest hotel still in operation is the Nishiyama Onsen Keiunkan in Japan, opened in 705 AD.",
        "The most expensive hotel room in the world is the Royal Villa at the Grand Resort Lagonissi in Greece, costing $50,000 per night.",
        "The world's highest hotel is the Ritz-Carlton Hong Kong, located on the 102nd to 118th floors.",
        "The largest hotel chain in the world is Marriott International with over 7,000 properties.",
        "The first hotel to offer room service was the Waldorf-Astoria in New York City in 1893.",
        "The world's most remote hotel is the Amundsen-Scott South Pole Station in Antarctica.",
        "The first hotel to have electricity was the Hotel Savoy in London in 1889.",
        "The world's largest hotel suite is the Royal Villa at the Grand Resort Lagonissi, covering 1,300 square meters.",
        "The first hotel to have a swimming pool was the Hotel del Coronado in San Diego in 1888.",
        "The world's most haunted hotel is said to be the Stanley Hotel in Colorado, which inspired Stephen King's 'The Shining'.",
        "The first hotel to have air conditioning was the Hotel Pennsylvania in New York City in 1925.",
        "The world's most expensive hotel room service meal was ordered at the Ritz Paris for €1,000.",
        "The first hotel to have a telephone in every room was the Hotel Pennsylvania in 1900.",
        "The world's largest hotel lobby is at the Venetian Macao, covering 550,000 square feet.",
        "The first hotel to have an elevator was the Hotel Astor in New York City in 1904.",
        "The world's most photographed hotel is the Burj Al Arab in Dubai.",
        "The first hotel to have a restaurant was the City Hotel in New York City in 1794.",
        "The world's most sustainable hotel is the Proximity Hotel in North Carolina, the first LEED Platinum hotel in the US."
    ];
    
    const randomFact = triviaFacts[Math.floor(Math.random() * triviaFacts.length)];
    const triviaContent = document.getElementById('trivia-content');
    
    if (triviaContent) {
        triviaContent.innerHTML = `<p class="text-lg leading-relaxed">${randomFact}</p>`;
    }
}

// Motivation refresh function
function refreshMotivation() {
    const motivationalQuotes = [
        "Excellence is not a skill. It's an attitude. - Ralph Marston",
        "The only way to do great work is to love what you do. - Steve Jobs",
        "Success is not final, failure is not fatal: it is the courage to continue that counts. - Winston Churchill",
        "The future belongs to those who believe in the beauty of their dreams. - Eleanor Roosevelt",
        "Quality is not an act, it is a habit. - Aristotle",
        "The best way to predict the future is to create it. - Peter Drucker",
        "Service to others is the rent you pay for your room here on earth. - Muhammad Ali",
        "The difference between ordinary and extraordinary is that little extra. - Jimmy Johnson",
        "Your work is going to fill a large part of your life, and the only way to be truly satisfied is to do what you believe is great work. - Steve Jobs",
        "The only limit to our realization of tomorrow will be our doubts of today. - Franklin D. Roosevelt"
    ];
    
    const randomQuote = motivationalQuotes[Math.floor(Math.random() * motivationalQuotes.length)];
    const motivationContent = document.getElementById('motivation-content');
    
    if (motivationContent) {
        motivationContent.innerHTML = `<p class="text-lg italic leading-relaxed">"${randomQuote}"</p>`;
    }
}

function initializeTrainingDashboard() {
    switchTrainingTab('scenarios');
    
    // Initialize filter change listeners
    document.getElementById('scenario-difficulty-filter').addEventListener('change', loadScenarios);
    document.getElementById('scenario-category-filter').addEventListener('change', loadScenarios);
    document.getElementById('service-type-filter').addEventListener('change', loadCustomerService);
    document.getElementById('problem-severity-filter').addEventListener('change', loadProblemScenarios);
}

// Tab switching functionality
function switchTrainingTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
        button.classList.remove('border-primary', 'text-primary');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    const selectedContent = document.getElementById(`tab-content-${tabName}`);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('active');
    }
    
    const selectedButton = document.getElementById(`tab-${tabName}`);
    if (selectedButton) {
        selectedButton.classList.add('active', 'border-primary', 'text-primary');
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
    }
    
    switch(tabName) {
        case 'scenarios': loadScenarios(); break;
        case 'customer-service': loadCustomerService(); break;
        case 'problems': loadProblemScenarios(); break;
        case 'progress': loadProgress(); break;
    }
}

// Load functions
function loadScenarios() {
    const container = document.getElementById('scenarios-container');
    const difficultyFilter = document.getElementById('scenario-difficulty-filter').value;
    const categoryFilter = document.getElementById('scenario-category-filter').value;
    
    // Show loading state
    container.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Loading Scenarios...</h3>
        </div>
    `;
    
    // Build query parameters
    const params = new URLSearchParams();
    if (difficultyFilter) params.append('difficulty', difficultyFilter);
    if (categoryFilter) params.append('category', categoryFilter);
    
    // Fetch scenarios from API
    fetch(`../../api/get-training-scenarios.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayScenarios(data.scenarios);
            } else {
                throw new Error(data.message || 'Failed to load scenarios');
            }
        })
        .catch(error => {
            console.error('Error loading scenarios:', error);
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Scenarios</h3>
                    <p class="text-gray-500 mb-4">Unable to load training scenarios. Please try again later.</p>
                    <button onclick="loadScenarios()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-refresh mr-2"></i>Retry
                    </button>
                </div>
            `;
        });
}

function loadCustomerService() {
    const container = document.getElementById('customer-service-container');
    const typeFilter = document.getElementById('service-type-filter').value;
    
    // Show loading state
    container.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Loading Customer Service Scenarios...</h3>
        </div>
    `;
    
    // For now, show sample data since we don't have a specific API for customer service scenarios
    setTimeout(() => {
        const sampleScenarios = [
            {
                id: 'customer_service',
                title: 'Handling Guest Complaints',
                description: 'Practice responding to common guest complaints professionally.',
                type: 'complaints',
                difficulty: 'beginner',
                estimated_time: 20,
                points: 150
            },
            {
                id: 'special_requests',
                title: 'Special Guest Requests',
                description: 'Handle unusual guest requests with professionalism and creativity.',
                type: 'requests',
                difficulty: 'intermediate',
                estimated_time: 25,
                points: 200
            }
        ];
        
        // Apply filter
        const filteredScenarios = typeFilter ? 
            sampleScenarios.filter(s => s.type === typeFilter) : 
            sampleScenarios;
        
        displayCustomerService(filteredScenarios);
    }, 500);
}

function loadProblemScenarios() {
    const container = document.getElementById('problem-scenarios-container');
    const severityFilter = document.getElementById('problem-severity-filter').value;
    
    // Show loading state
    container.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Loading Problem Scenarios...</h3>
        </div>
    `;
    
    // For now, show sample data since we don't have a specific API for problem scenarios
    setTimeout(() => {
        const sampleScenarios = [
            {
                id: 'problem_solving',
                title: 'System Failure Response',
                description: 'Handle a critical system failure during peak hours.',
                severity: 'high',
                difficulty: 'advanced',
                time_limit: 10,
                points: 300
            },
            {
                id: 'emergency_response',
                title: 'Emergency Situation',
                description: 'Respond to an emergency situation in the hotel.',
                severity: 'critical',
                difficulty: 'advanced',
                time_limit: 15,
                points: 400
            }
        ];
        
        // Apply filter
        const filteredScenarios = severityFilter ? 
            sampleScenarios.filter(s => s.severity === severityFilter) : 
            sampleScenarios;
        
        displayProblemScenarios(filteredScenarios);
    }, 500);
}

function loadProgress() {
    const container = document.getElementById('progress-container');
    
    // Show loading state
    container.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Loading Progress...</h3>
        </div>
    `;
    
    // For now, show sample progress data
    setTimeout(() => {
        const sampleProgress = {
            completion_rate: 75,
            average_score: 85,
            total_points: 1250,
            recent_activity: [
                {
                    scenario_title: 'Front Desk Check-in Process',
                    completed_at: new Date().toISOString(),
                    score: 90,
                    points: 100
                },
                {
                    scenario_title: 'Handling Guest Complaints',
                    completed_at: new Date(Date.now() - 86400000).toISOString(),
                    score: 85,
                    points: 150
                }
            ],
            certificates: [
                {
                    name: 'Front Desk Operations',
                    earned_at: new Date(Date.now() - 172800000).toISOString()
                },
                {
                    name: 'Customer Service Excellence',
                    earned_at: new Date(Date.now() - 259200000).toISOString()
                }
            ]
        };
        
        displayProgress(sampleProgress);
    }, 500);
}

// Display functions
function displayScenarios(scenarios) {
    const container = document.getElementById('scenarios-container');
    
    if (!scenarios || scenarios.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-play-circle text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No scenarios found</h3>
                <p class="text-gray-500">No scenarios match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const gridHtml = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            ${scenarios.map(scenario => `
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-medium text-gray-900">${scenario.title}</h4>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getDifficultyClass(scenario.difficulty)}">
                                ${getDifficultyLabel(scenario.difficulty)}
                            </span>
                        </div>
                        <p class="text-gray-600 mb-4">${scenario.description}</p>
                        <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                            <span><i class="fas fa-clock mr-1"></i>${scenario.estimated_time} min</span>
                            <span><i class="fas fa-star mr-1"></i>${scenario.points} points</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">${getCategoryLabel(scenario.category)}</span>
                            <button onclick="startScenario('${scenario.id}')" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                                <i class="fas fa-play mr-2"></i>Start
                            </button>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    
    container.innerHTML = gridHtml;
}

function displayCustomerService(scenarios) {
    const container = document.getElementById('customer-service-container');
    
    if (!scenarios || scenarios.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-headset text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No customer service scenarios found</h3>
                <p class="text-gray-500">No scenarios match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const gridHtml = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            ${scenarios.map(scenario => `
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-medium text-gray-900">${scenario.title}</h4>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getServiceTypeClass(scenario.type)}">
                                ${getServiceTypeLabel(scenario.type)}
                            </span>
                        </div>
                        <p class="text-gray-600 mb-4">${scenario.description}</p>
                        <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                            <span><i class="fas fa-clock mr-1"></i>${scenario.estimated_time} min</span>
                            <span><i class="fas fa-star mr-1"></i>${scenario.points} points</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">${getDifficultyLabel(scenario.difficulty)}</span>
                            <button onclick="startCustomerService('${scenario.id}')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                <i class="fas fa-play mr-2"></i>Practice
                            </button>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    
    container.innerHTML = gridHtml;
}

function displayProblemScenarios(scenarios) {
    const container = document.getElementById('problem-scenarios-container');
    
    if (!scenarios || scenarios.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No problem scenarios found</h3>
                <p class="text-gray-500">No scenarios match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const gridHtml = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            ${scenarios.map(scenario => `
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-medium text-gray-900">${scenario.title}</h4>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getSeverityClass(scenario.severity)}">
                                ${getSeverityLabel(scenario.severity)}
                            </span>
                        </div>
                        <p class="text-gray-600 mb-4">${scenario.description}</p>
                        <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                            <span><i class="fas fa-clock mr-1"></i>${scenario.time_limit} min</span>
                            <span><i class="fas fa-star mr-1"></i>${scenario.points} points</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">${getDifficultyLabel(scenario.difficulty)}</span>
                            <button onclick="startProblemScenario('${scenario.id}')" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                                <i class="fas fa-play mr-2"></i>Solve
                            </button>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    
    container.innerHTML = gridHtml;
}

function displayProgress(progress) {
    const container = document.getElementById('progress-container');
    
    const progressHtml = `
        <div class="space-y-6">
            <!-- Overall Progress -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Overall Progress</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">${progress.completion_rate}%</div>
                        <div class="text-sm text-gray-500">Completion Rate</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">${progress.average_score}%</div>
                        <div class="text-sm text-gray-500">Average Score</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">${progress.total_points}</div>
                        <div class="text-sm text-gray-500">Total Points</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Recent Activity</h4>
                <div class="space-y-3">
                    ${progress.recent_activity.map(activity => `
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-play text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">${activity.scenario_title}</div>
                                    <div class="text-sm text-gray-500">${formatDate(activity.completed_at)}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-medium text-gray-900">${activity.score}%</div>
                                <div class="text-sm text-gray-500">${activity.points} points</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <!-- Certificates -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Certificates Earned</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    ${progress.certificates.map(certificate => `
                        <div class="flex items-center p-4 border border-gray-200 rounded-lg">
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-certificate text-yellow-600 text-xl"></i>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">${certificate.name}</div>
                                <div class="text-sm text-gray-500">Earned on ${formatDate(certificate.earned_at)}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = progressHtml;
}

// Scenario functions
function startScenario(scenarioId) {
    fetch(`../../api/get-scenario-details.php?id=${scenarioId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openScenarioModal(data.scenario);
            } else {
                Utils.showNotification(data.message || 'Error loading scenario', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading scenario:', error);
            Utils.showNotification('Error loading scenario', 'error');
        });
}

function openScenarioModal(scenario) {
    document.getElementById('scenario-title').textContent = scenario.title;
    document.getElementById('scenario-content').innerHTML = `
        <div class="space-y-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-medium text-blue-900 mb-2">Scenario Description</h4>
                <p class="text-blue-800">${scenario.description}</p>
            </div>
            
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 mb-2">Instructions</h4>
                <p class="text-gray-700">${scenario.instructions}</p>
            </div>
            
            <div class="space-y-4">
                ${scenario.questions.map((question, index) => `
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h5 class="font-medium text-gray-900 mb-2">Question ${index + 1}</h5>
                        <p class="text-gray-700 mb-3">${question.question}</p>
                        <div class="space-y-2">
                            ${question.options.map(option => `
                                <label class="flex items-center">
                                    <input type="radio" name="q${index}" value="${option.value}" class="mr-2">
                                    <span class="text-gray-700">${option.text}</span>
                                </label>
                            `).join('')}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    document.getElementById('scenario-modal').classList.remove('hidden');
    startScenarioTimer();
}

function closeScenarioModal() {
    document.getElementById('scenario-modal').classList.add('hidden');
    stopScenarioTimer();
}

function startScenarioTimer() {
    // Timer implementation
    let seconds = 0;
    window.scenarioTimer = setInterval(() => {
        seconds++;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        document.getElementById('scenario-timer').textContent = 
            `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
    }, 1000);
}

function stopScenarioTimer() {
    if (window.scenarioTimer) {
        clearInterval(window.scenarioTimer);
        window.scenarioTimer = null;
    }
}

function pauseScenario() {
    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn.innerHTML.includes('Pause')) {
        pauseBtn.innerHTML = '<i class="fas fa-play mr-2"></i>Resume';
        stopScenarioTimer();
    } else {
        pauseBtn.innerHTML = '<i class="fas fa-pause mr-2"></i>Pause';
        startScenarioTimer();
    }
}

function submitScenario() {
    // Collect answers
    const answers = {};
    const questions = document.querySelectorAll('[name^="q"]');
    questions.forEach(question => {
        const selected = document.querySelector(`input[name="${question.name}"]:checked`);
        if (selected) {
            answers[question.name] = selected.value;
        }
    });
    
    // Submit answers
    fetch('../../api/submit-scenario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(answers)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Utils.showNotification(`Scenario completed! Score: ${data.score}%`, 'success');
            closeScenarioModal();
            loadScenarios();
            loadProgress();
        } else {
            Utils.showNotification(data.message || 'Error submitting scenario', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting scenario:', error);
        Utils.showNotification('Error submitting scenario', 'error');
    });
}

// Customer service functions
function startCustomerService(scenarioId) {
    fetch(`../../api/get-customer-service-details.php?id=${scenarioId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openCustomerServiceModal(data.scenario);
            } else {
                Utils.showNotification(data.message || 'Error loading customer service scenario', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading customer service scenario:', error);
            Utils.showNotification('Error loading customer service scenario', 'error');
        });
}

function openCustomerServiceModal(scenario) {
    document.getElementById('cs-title').textContent = scenario.title;
    document.getElementById('cs-difficulty').textContent = getDifficultyLabel(scenario.difficulty);
    document.getElementById('cs-points').textContent = scenario.points;
    
    document.getElementById('customer-service-content').innerHTML = `
        <div class="space-y-6">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="font-medium text-green-900 mb-2">Customer Situation</h4>
                <p class="text-green-800">${scenario.situation}</p>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="font-medium text-yellow-900 mb-2">Guest Request/Complaint</h4>
                <p class="text-yellow-800">${scenario.guest_request}</p>
            </div>
            
            <div class="space-y-4">
                <label class="block">
                    <span class="text-gray-700 font-medium">Your Response:</span>
                    <textarea id="customer-service-response" rows="4" 
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
                              placeholder="Type your response to the guest..."></textarea>
                </label>
                
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h5 class="font-medium text-gray-900 mb-2">Tips for Good Customer Service:</h5>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li>• Listen actively and acknowledge the guest's concerns</li>
                        <li>• Show empathy and understanding</li>
                        <li>• Offer specific solutions or alternatives</li>
                        <li>• Follow up to ensure satisfaction</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('customer-service-modal').classList.remove('hidden');
}

function closeCustomerServiceModal() {
    document.getElementById('customer-service-modal').classList.add('hidden');
}

function skipCustomerService() {
    Utils.showNotification('Scenario skipped', 'info');
    closeCustomerServiceModal();
}

function submitCustomerService() {
    const response = document.getElementById('customer-service-response').value;
    
    if (!response.trim()) {
        Utils.showNotification('Please provide a response', 'warning');
        return;
    }
    
    fetch('../../api/submit-customer-service.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ response: response })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Utils.showNotification(`Response submitted! Score: ${data.score}%`, 'success');
            closeCustomerServiceModal();
            loadCustomerService();
            loadProgress();
        } else {
            Utils.showNotification(data.message || 'Error submitting response', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting response:', error);
        Utils.showNotification('Error submitting response', 'error');
    });
}

// Problem scenario functions
function startProblemScenario(scenarioId) {
    fetch(`../../api/get-problem-details.php?id=${scenarioId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openProblemModal(data.scenario);
            } else {
                Utils.showNotification(data.message || 'Error loading problem scenario', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading problem scenario:', error);
            Utils.showNotification('Error loading problem scenario', 'error');
        });
}

function openProblemModal(scenario) {
    document.getElementById('problem-title').textContent = scenario.title;
    document.getElementById('problem-severity').textContent = getSeverityLabel(scenario.severity);
    
    document.getElementById('problem-content').innerHTML = `
        <div class="space-y-6">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="font-medium text-red-900 mb-2">Problem Description</h4>
                <p class="text-red-800">${scenario.description}</p>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="font-medium text-yellow-900 mb-2">Available Resources</h4>
                <p class="text-yellow-800">${scenario.resources}</p>
            </div>
            
            <div class="space-y-4">
                <label class="block">
                    <span class="text-gray-700 font-medium">Your Solution:</span>
                    <textarea id="problem-solution" rows="4" 
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
                              placeholder="Describe your solution to the problem..."></textarea>
                </label>
                
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h5 class="font-medium text-gray-900 mb-2">Problem-Solving Steps:</h5>
                    <ol class="text-sm text-gray-700 space-y-1">
                        <li>1. Identify the root cause of the problem</li>
                        <li>2. Assess the impact and urgency</li>
                        <li>3. Consider multiple solution options</li>
                        <li>4. Choose the best solution</li>
                        <li>5. Implement and monitor the solution</li>
                    </ol>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('problem-modal').classList.remove('hidden');
    startProblemTimer(scenario.time_limit);
}

function closeProblemModal() {
    document.getElementById('problem-modal').classList.add('hidden');
    stopProblemTimer();
}

function startProblemTimer(timeLimit) {
    let timeLeft = timeLimit * 60; // Convert to seconds
    window.problemTimer = setInterval(() => {
        timeLeft--;
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        document.getElementById('problem-timer').textContent = 
            `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            stopProblemTimer();
            Utils.showNotification('Time is up!', 'warning');
        }
    }, 1000);
}

function stopProblemTimer() {
    if (window.problemTimer) {
        clearInterval(window.problemTimer);
        window.problemTimer = null;
    }
}

function requestHint() {
    Utils.showNotification('Hint: Consider the guest\'s perspective and hotel policies', 'info');
}

function submitProblem() {
    const solution = document.getElementById('problem-solution').value;
    
    if (!solution.trim()) {
        Utils.showNotification('Please provide a solution', 'warning');
        return;
    }
    
    fetch('../../api/submit-problem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ solution: solution })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Utils.showNotification(`Solution submitted! Score: ${data.score}%`, 'success');
            closeProblemModal();
            loadProblemScenarios();
            loadProgress();
        } else {
            Utils.showNotification(data.message || 'Error submitting solution', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting solution:', error);
        Utils.showNotification('Error submitting solution', 'error');
    });
}

// Utility functions
function exportProgress() {
    fetch('../../api/export-training-progress.php')
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `training_progress_${new Date().toISOString().split('T')[0]}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            Utils.showNotification('Progress exported successfully!', 'success');
        })
        .catch(error => {
            console.error('Error exporting progress:', error);
            Utils.showNotification('Error exporting progress', 'error');
        });
}

// Helper functions for styling
function getDifficultyClass(difficulty) {
    switch (difficulty) {
        case 'beginner': return 'bg-green-100 text-green-800';
        case 'intermediate': return 'bg-yellow-100 text-yellow-800';
        case 'advanced': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getDifficultyLabel(difficulty) {
    return difficulty.charAt(0).toUpperCase() + difficulty.slice(1);
}

function getCategoryLabel(category) {
    switch (category) {
        case 'front_desk': return 'Front Desk';
        case 'housekeeping': return 'Housekeeping';
        case 'management': return 'Management';
        default: return category;
    }
}

function getServiceTypeClass(type) {
    switch (type) {
        case 'complaints': return 'bg-red-100 text-red-800';
        case 'requests': return 'bg-blue-100 text-blue-800';
        case 'emergencies': return 'bg-orange-100 text-orange-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getServiceTypeLabel(type) {
    return type.charAt(0).toUpperCase() + type.slice(1);
}

function getSeverityClass(severity) {
    switch (severity) {
        case 'low': return 'bg-green-100 text-green-800';
        case 'medium': return 'bg-yellow-100 text-yellow-800';
        case 'high': return 'bg-orange-100 text-orange-800';
        case 'critical': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getSeverityLabel(severity) {
    return severity.charAt(0).toUpperCase() + severity.slice(1);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
