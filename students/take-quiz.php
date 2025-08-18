<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$student_id = $_SESSION['user_id'];

if (!$quiz_id) {
    header('Location: dashboard.php');
    exit();
}

// Get quiz details
$quiz_query = "SELECT q.*, tc.section, cc.subject_title, tc.join_code
               FROM quizzes q
               JOIN teacher_classes tc ON q.class_id = tc.id
               JOIN course_curriculum cc ON tc.subject_id = cc.id
               JOIN class_enrollments ce ON tc.id = ce.class_id
               WHERE q.id = ? AND ce.student_id = ? AND ce.status = 'active'";
$quiz_stmt = mysqli_prepare($conn, $quiz_query);
mysqli_stmt_bind_param($quiz_stmt, "ii", $quiz_id, $student_id);
mysqli_stmt_execute($quiz_stmt);
$quiz_result = mysqli_stmt_get_result($quiz_stmt);

if (mysqli_num_rows($quiz_result) === 0) {
    header('Location: dashboard.php');
    exit();
}

$quiz = mysqli_fetch_assoc($quiz_result);

// Check if student has already started/completed this quiz
$submission_query = "SELECT * FROM quiz_submissions WHERE quiz_id = ? AND student_id = ?";
$submission_stmt = mysqli_prepare($conn, $submission_query);
mysqli_stmt_bind_param($submission_stmt, "ii", $quiz_id, $student_id);
mysqli_stmt_execute($submission_stmt);
$submission_result = mysqli_stmt_get_result($submission_stmt);
$existing_submission = mysqli_fetch_assoc($submission_result);

// Handle quiz start
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'start_quiz' && !$existing_submission) {
        // Create new submission
        $insert_query = "INSERT INTO quiz_submissions (quiz_id, student_id, status, started_at, updated_at)
                        VALUES (?, ?, 'started', NOW(), NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ii", $quiz_id, $student_id);

        if (mysqli_stmt_execute($insert_stmt)) {
            $submission_id = mysqli_insert_id($conn);
            header("Location: take-quiz.php?quiz_id=$quiz_id&submission_id=$submission_id");
            exit();
        }
    }
}

// Get quiz questions
$questions_query = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC";
$questions_stmt = mysqli_prepare($conn, $questions_query);
mysqli_stmt_bind_param($questions_stmt, "i", $quiz_id);
mysqli_stmt_execute($questions_stmt);
$questions_result = mysqli_stmt_get_result($questions_stmt);

$questions = [];
while ($question = mysqli_fetch_assoc($questions_result)) {
    // Get options for multiple choice questions
    if ($question['question_type'] === 'multiple_choice') {
        $options_query = "SELECT * FROM quiz_question_options WHERE question_id = ? ORDER BY option_order ASC";
        $options_stmt = mysqli_prepare($conn, $options_query);
        mysqli_stmt_bind_param($options_stmt, "i", $question['id']);
        mysqli_stmt_execute($options_stmt);
        $options_result = mysqli_stmt_get_result($options_stmt);

        $question['options'] = [];
        while ($option = mysqli_fetch_assoc($options_result)) {
            $question['options'][] = $option;
        }
    }
    $questions[] = $question;
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Quiz Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($quiz['subject_title'] . ' - ' . $quiz['section']); ?></p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Time Remaining</div>
                        <div class="text-xl font-bold text-seait-orange" id="timer">--:--</div>
                    </div>
                    <button onclick="toggleLeaderboard()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-trophy mr-2"></i>Leaderboard
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Quiz Content -->
            <div class="lg:col-span-3">
                <?php if (!$existing_submission): ?>
                    <!-- Quiz Start Screen -->
                    <div class="bg-white rounded-lg shadow-md p-8 text-center">
                        <div class="mb-8">
                            <i class="fas fa-question-circle text-6xl text-seait-orange mb-4"></i>
                            <h2 class="text-3xl font-bold text-gray-900 mb-4">Ready to Start?</h2>
                            <p class="text-gray-600 text-lg mb-6">You're about to begin: <strong><?php echo htmlspecialchars($quiz['title']); ?></strong></p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <i class="fas fa-list-ol text-2xl text-blue-600 mb-2"></i>
                                <div class="text-lg font-semibold text-gray-900"><?php echo count($questions); ?></div>
                                <div class="text-sm text-gray-600">Questions</div>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4">
                                <i class="fas fa-clock text-2xl text-green-600 mb-2"></i>
                                <div class="text-lg font-semibold text-gray-900"><?php echo $quiz['time_limit']; ?> min</div>
                                <div class="text-sm text-gray-600">Time Limit</div>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-4">
                                <i class="fas fa-star text-2xl text-purple-600 mb-2"></i>
                                <div class="text-lg font-semibold text-gray-900"><?php echo $quiz['total_points']; ?></div>
                                <div class="text-sm text-gray-600">Total Points</div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8">
                            <h3 class="font-semibold text-yellow-800 mb-2">Important Instructions:</h3>
                            <ul class="text-yellow-700 text-left space-y-1">
                                <li>• You cannot pause or restart the quiz once started</li>
                                <li>• Ensure you have a stable internet connection</li>
                                <li>• Submit your answers before time runs out</li>
                                <li>• You can review and change answers before submitting</li>
                            </ul>
                        </div>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="start_quiz">
                            <button type="submit" class="bg-seait-orange text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-orange-600 transition transform hover:scale-105">
                                <i class="fas fa-play mr-2"></i>Start Quiz
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Quiz Interface -->
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-semibold text-gray-900">Question <span id="currentQuestion">1</span> of <?php echo count($questions); ?></h2>
                                <div class="flex items-center space-x-4">
                                    <div class="text-sm text-gray-600">
                                        Progress: <span id="progressPercent">0</span>%
                                    </div>
                                    <div class="w-32 bg-gray-200 rounded-full h-2">
                                        <div class="bg-seait-orange h-2 rounded-full transition-all duration-300" id="progressBar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <div id="questionContainer">
                                <!-- Questions will be loaded here -->
                            </div>

                            <div class="flex justify-between mt-8">
                                <button onclick="previousQuestion()" id="prevBtn" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-arrow-left mr-2"></i>Previous
                                </button>
                                <button onclick="nextQuestion()" id="nextBtn" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                                    Next<i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Live Leaderboard Sidebar -->
            <div class="lg:col-span-1">
                <div id="leaderboardSidebar" class="bg-white rounded-lg shadow-md p-6 sticky top-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Live Leaderboard</h3>
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    </div>

                    <div id="leaderboardContent" class="space-y-3">
                        <div class="text-center text-gray-500 py-8">
                            <i class="fas fa-trophy text-2xl mb-2"></i>
                            <p class="text-sm">Loading leaderboard...</p>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="text-center">
                            <button onclick="refreshLeaderboard()" class="text-seait-orange hover:text-orange-600 text-sm">
                                <i class="fas fa-sync-alt mr-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quiz Completion Modal -->
<div id="completionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                <i class="fas fa-check text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quiz Completed!</h3>
            <div id="completionStats" class="mb-6">
                <!-- Stats will be populated here -->
            </div>
            <div class="flex justify-center space-x-3">
                <button onclick="viewResults()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    View Results
                </button>
                <button onclick="closeModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.question-card {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.option-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.option-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.option-card.selected {
    border-color: #FF6B35;
    background-color: #FFF8F0;
}

.option-card.correct {
    border-color: #10B981;
    background-color: #F0FDF4;
}

.option-card.incorrect {
    border-color: #EF4444;
    background-color: #FEF2F2;
}

.leaderboard-item {
    animation: slideInFromRight 0.3s ease-out;
}

@keyframes slideInFromRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.rank-badge {
    transition: all 0.3s ease;
}

.rank-badge:hover {
    transform: scale(1.1);
}

.progress-animation {
    transition: width 0.5s ease-out;
}

.timer-warning {
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>

<script>
let currentQuestionIndex = 0;
let answers = {};
let startTime = new Date();
let timerInterval;
let leaderboardInterval;
const questions = <?php echo json_encode($questions); ?>;
const quiz = <?php echo json_encode($quiz); ?>;
const submissionId = <?php echo $existing_submission ? $existing_submission['id'] : 'null'; ?>;

// Initialize quiz
document.addEventListener('DOMContentLoaded', function() {
    if (submissionId) {
        loadQuestion(currentQuestionIndex);
        startTimer();
        startLeaderboardUpdates();
        loadSavedAnswers();
    }
});

// Load question
function loadQuestion(index) {
    const question = questions[index];
    const container = document.getElementById('questionContainer');

    let optionsHtml = '';
    if (question.question_type === 'multiple_choice') {
        question.options.forEach((option, optionIndex) => {
            const isSelected = answers[question.id] === option.id;
            optionsHtml += `
                <div class="option-card border-2 rounded-lg p-4 mb-3 ${isSelected ? 'selected' : 'border-gray-200'}"
                     onclick="selectOption(${question.id}, ${option.id})">
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded-full border-2 border-gray-300 mr-3 flex items-center justify-center">
                            ${isSelected ? '<div class="w-3 h-3 bg-seait-orange rounded-full"></div>' : ''}
                        </div>
                        <span class="text-gray-900">${option.option_text}</span>
                    </div>
                </div>
            `;
        });
    } else if (question.question_type === 'true_false') {
        const options = [
            { id: 'true', option_text: 'True' },
            { id: 'false', option_text: 'False' }
        ];
        options.forEach(option => {
            const isSelected = answers[question.id] === option.id;
            optionsHtml += `
                <div class="option-card border-2 rounded-lg p-4 mb-3 ${isSelected ? 'selected' : 'border-gray-200'}"
                     onclick="selectOption(${question.id}, '${option.id}')">
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded-full border-2 border-gray-300 mr-3 flex items-center justify-center">
                            ${isSelected ? '<div class="w-3 h-3 bg-seait-orange rounded-full"></div>' : ''}
                        </div>
                        <span class="text-gray-900">${option.option_text}</span>
                    </div>
                </div>
            `;
        });
    }

    container.innerHTML = `
        <div class="question-card">
            <div class="mb-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">${question.question_text}</h3>
                ${question.question_image ? `<img src="${question.question_image}" alt="Question Image" class="max-w-full h-auto rounded-lg mb-4">` : ''}
            </div>
            <div class="space-y-3">
                ${optionsHtml}
            </div>
        </div>
    `;

    updateNavigation();
    updateProgress();
}

// Select option
function selectOption(questionId, optionId) {
    answers[questionId] = optionId;
    saveAnswer(questionId, optionId);
    loadQuestion(currentQuestionIndex); // Reload to show selection
}

// Save answer
function saveAnswer(questionId, optionId) {
    fetch('../api/save-quiz-answer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            submission_id: submissionId,
            question_id: questionId,
            answer: optionId
        })
    }).catch(error => console.error('Error saving answer:', error));
}

// Navigation functions
function nextQuestion() {
    if (currentQuestionIndex < questions.length - 1) {
        currentQuestionIndex++;
        loadQuestion(currentQuestionIndex);
    } else {
        submitQuiz();
    }
}

function previousQuestion() {
    if (currentQuestionIndex > 0) {
        currentQuestionIndex--;
        loadQuestion(currentQuestionIndex);
    }
}

function updateNavigation() {
    document.getElementById('currentQuestion').textContent = currentQuestionIndex + 1;
    document.getElementById('prevBtn').disabled = currentQuestionIndex === 0;

    const nextBtn = document.getElementById('nextBtn');
    if (currentQuestionIndex === questions.length - 1) {
        nextBtn.innerHTML = 'Submit Quiz<i class="fas fa-check ml-2"></i>';
        nextBtn.className = 'bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition';
    } else {
        nextBtn.innerHTML = 'Next<i class="fas fa-arrow-right ml-2"></i>';
        nextBtn.className = 'bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition';
    }
}

function updateProgress() {
    const progress = ((currentQuestionIndex + 1) / questions.length) * 100;
    document.getElementById('progressPercent').textContent = Math.round(progress);
    document.getElementById('progressBar').style.width = progress + '%';
}

// Timer functions
function startTimer() {
    const timeLimit = quiz.time_limit * 60; // Convert to seconds
    let timeRemaining = timeLimit;

    timerInterval = setInterval(() => {
        timeRemaining--;
        updateTimer(timeRemaining);

        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            submitQuiz();
        }
    }, 1000);
}

function updateTimer(seconds) {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    const timerElement = document.getElementById('timer');

    timerElement.textContent = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;

    if (seconds <= 300) { // 5 minutes warning
        timerElement.classList.add('timer-warning', 'text-red-600');
    }
}

// Leaderboard functions
function startLeaderboardUpdates() {
    updateLeaderboard();
    leaderboardInterval = setInterval(updateLeaderboard, 5000); // Update every 5 seconds
}

function updateLeaderboard() {
    fetch(`../api/get-quiz-leaderboard.php?quiz_id=${quiz.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLeaderboard(data.leaderboard);
            }
        })
        .catch(error => console.error('Error updating leaderboard:', error));
}

function displayLeaderboard(leaderboard) {
    const container = document.getElementById('leaderboardContent');

    if (leaderboard.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-4">
                <i class="fas fa-trophy text-2xl mb-2"></i>
                <p class="text-sm">No submissions yet</p>
            </div>
        `;
        return;
    }

    let html = '';
    leaderboard.slice(0, 10).forEach((student, index) => {
        const rankClass = index < 3 ?
            (index === 0 ? 'bg-yellow-100 text-yellow-800' :
             index === 1 ? 'bg-gray-100 text-gray-800' : 'bg-orange-100 text-orange-800') :
            'bg-gray-100 text-gray-600';

        html += `
            <div class="leaderboard-item flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-3">
                    <div class="rank-badge w-6 h-6 rounded-full ${rankClass} flex items-center justify-center text-xs font-bold">
                        ${index + 1}
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">${student.first_name} ${student.last_name}</div>
                        <div class="text-xs text-gray-500">${student.score} pts</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold text-gray-900">${student.percentage}%</div>
                    <div class="text-xs text-gray-500">${formatTime(student.time_taken)}</div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function refreshLeaderboard() {
    updateLeaderboard();
}

function toggleLeaderboard() {
    const sidebar = document.getElementById('leaderboardSidebar');
    sidebar.classList.toggle('hidden');
}

// Submit quiz
function submitQuiz() {
    clearInterval(timerInterval);
    clearInterval(leaderboardInterval);

    const endTime = new Date();
    const timeTaken = Math.floor((endTime - startTime) / 1000);

    fetch('../api/submit-quiz.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            submission_id: submissionId,
            answers: answers,
            time_taken: timeTaken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showCompletionModal(data.results);
        }
    })
    .catch(error => {
        console.error('Error submitting quiz:', error);
        alert('Error submitting quiz. Please try again.');
    });
}

function showCompletionModal(results) {
    const modal = document.getElementById('completionModal');
    const stats = document.getElementById('completionStats');

    stats.innerHTML = `
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600">${results.score}</div>
                <div class="text-sm text-gray-600">Score</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">${results.percentage}%</div>
                <div class="text-sm text-gray-600">Accuracy</div>
            </div>
        </div>
        <div class="text-center">
            <div class="text-lg font-semibold text-gray-900">${results.correct_answers}/${results.total_questions} correct</div>
            <div class="text-sm text-gray-600">Time taken: ${formatTime(results.time_taken)}</div>
        </div>
    `;

    modal.classList.remove('hidden');
}

function viewResults() {
    window.location.href = `quiz-results.php?submission_id=${submissionId}`;
}

function closeModal() {
    document.getElementById('completionModal').classList.add('hidden');
    window.location.href = 'dashboard.php';
}

// Load saved answers
function loadSavedAnswers() {
    if (submissionId) {
        fetch(`../api/get-quiz-answers.php?submission_id=${submissionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    answers = data.answers;
                }
            })
            .catch(error => console.error('Error loading answers:', error));
    }
}

// Utility functions
function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft' && currentQuestionIndex > 0) {
        previousQuestion();
    } else if (e.key === 'ArrowRight' && currentQuestionIndex < questions.length - 1) {
        nextQuestion();
    } else if (e.key === 'Enter' && currentQuestionIndex === questions.length - 1) {
        submitQuiz();
    }
});

// Prevent accidental navigation
window.addEventListener('beforeunload', function(e) {
    if (submissionId && Object.keys(answers).length > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>

<?php include 'includes/footer.php'; ?>