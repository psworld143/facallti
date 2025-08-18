<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
if (!$quiz_id) {
    header('Location: quizzes.php');
    exit();
}

// Get quiz details
$quiz_query = "SELECT q.*, tc.section, cc.subject_title
               FROM quizzes q
               JOIN teacher_classes tc ON q.class_id = tc.id
               JOIN course_curriculum cc ON tc.subject_id = cc.id
               WHERE q.id = ? AND tc.teacher_id = ?";
$quiz_stmt = mysqli_prepare($conn, $quiz_query);
mysqli_stmt_bind_param($quiz_stmt, "ii", $quiz_id, $_SESSION['user_id']);
mysqli_stmt_execute($quiz_stmt);
$quiz_result = mysqli_stmt_get_result($quiz_stmt);

if (mysqli_num_rows($quiz_result) === 0) {
    header('Location: quizzes.php');
    exit();
}

$quiz = mysqli_fetch_assoc($quiz_result);

// Get current leaderboard data
$leaderboard_query = "SELECT
                        s.first_name, s.last_name, s.student_id,
                        qs.score, qs.total_questions, qs.correct_answers,
                        qs.time_taken, qs.completed_at,
                        ROUND((qs.correct_answers / qs.total_questions) * 100, 2) as percentage
                      FROM quiz_submissions qs
                      JOIN students s ON qs.student_id = s.id
                      WHERE qs.quiz_id = ? AND qs.status = 'completed'
                      ORDER BY qs.score DESC, qs.time_taken ASC, qs.completed_at ASC";
$leaderboard_stmt = mysqli_prepare($conn, $leaderboard_query);
mysqli_stmt_bind_param($leaderboard_stmt, "i", $quiz_id);
mysqli_stmt_execute($leaderboard_stmt);
$leaderboard_result = mysqli_stmt_get_result($leaderboard_stmt);

$leaderboard_data = [];
$rank = 1;
while ($row = mysqli_fetch_assoc($leaderboard_result)) {
    $row['rank'] = $rank;
    $leaderboard_data[] = $row;
    $rank++;
}

// Get quiz statistics
$stats_query = "SELECT
                  COUNT(*) as total_submissions,
                  COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_submissions,
                  AVG(CASE WHEN status = 'completed' THEN score END) as average_score,
                  MAX(CASE WHEN status = 'completed' THEN score END) as highest_score,
                  MIN(CASE WHEN status = 'completed' THEN time_taken END) as fastest_time
                FROM quiz_submissions
                WHERE quiz_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $quiz_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$quiz_stats = mysqli_fetch_assoc($stats_result);

include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Quiz Leaderboard</h1>
            <p class="text-gray-600 mt-1">Live results for <?php echo htmlspecialchars($quiz['title']); ?></p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <button onclick="toggleLiveMode()" id="liveModeBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-broadcast-tower mr-2"></i>Live Mode: ON
            </button>
            <a href="view-quiz.php?id=<?php echo $quiz_id; ?>" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>Back to Quiz
            </a>
        </div>
    </div>
</div>

<!-- Quiz Information -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($quiz['title']); ?></h3>
            <p class="text-gray-600"><?php echo htmlspecialchars($quiz['subject_title'] . ' - ' . $quiz['section']); ?></p>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-seait-orange"><?php echo $quiz_stats['completed_submissions']; ?></div>
            <div class="text-sm text-gray-600">Students Completed</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-green-600"><?php echo round($quiz_stats['average_score'], 1); ?></div>
            <div class="text-sm text-gray-600">Average Score</div>
        </div>
    </div>
</div>

<!-- Live Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm opacity-90">Total Submissions</p>
                <p class="text-2xl font-bold" id="totalSubmissions"><?php echo $quiz_stats['total_submissions']; ?></p>
            </div>
            <i class="fas fa-users text-3xl opacity-50"></i>
        </div>
    </div>

    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm opacity-90">Completed</p>
                <p class="text-2xl font-bold" id="completedSubmissions"><?php echo $quiz_stats['completed_submissions']; ?></p>
            </div>
            <i class="fas fa-check-circle text-3xl opacity-50"></i>
        </div>
    </div>

    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg shadow-md p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm opacity-90">Highest Score</p>
                <p class="text-2xl font-bold" id="highestScore"><?php echo $quiz_stats['highest_score']; ?></p>
            </div>
            <i class="fas fa-trophy text-3xl opacity-50"></i>
        </div>
    </div>

    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm opacity-90">Fastest Time</p>
                <p class="text-2xl font-bold" id="fastestTime"><?php echo $quiz_stats['fastest_time'] ? gmdate('i:s', $quiz_stats['fastest_time']) : 'N/A'; ?></p>
            </div>
            <i class="fas fa-stopwatch text-3xl opacity-50"></i>
        </div>
    </div>
</div>

<!-- Live Leaderboard -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900">Live Leaderboard</h2>
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm text-gray-600" id="lastUpdated">Live updates enabled</span>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Accuracy</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="leaderboardBody">
                <?php if (empty($leaderboard_data)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-trophy text-4xl mb-4 text-gray-300"></i>
                        <p>No submissions yet. Students will appear here as they complete the quiz.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($leaderboard_data as $index => $student): ?>
                    <tr class="leaderboard-row <?php echo $index < 3 ? 'top-three' : ''; ?>" data-rank="<?php echo $student['rank']; ?>">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <?php if ($student['rank'] <= 3): ?>
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm
                                        <?php echo $student['rank'] == 1 ? 'bg-yellow-500' : ($student['rank'] == 2 ? 'bg-gray-400' : 'bg-orange-500'); ?>">
                                        <?php echo $student['rank']; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-900 font-medium"><?php echo $student['rank']; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                    <span class="text-white font-medium text-sm">
                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-lg font-bold text-gray-900"><?php echo $student['score']; ?></div>
                            <div class="text-sm text-gray-500"><?php echo $student['correct_answers']; ?>/<?php echo $student['total_questions']; ?> correct</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-green-500 h-2 rounded-full transition-all duration-500"
                                         style="width: <?php echo $student['percentage']; ?>%"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-900"><?php echo $student['percentage']; ?>%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo gmdate('i:s', $student['time_taken']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M j, g:i A', strtotime($student['completed_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Live Activity Feed -->
<div class="bg-white rounded-lg shadow-md p-6 mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Live Activity Feed</h3>
    <div id="activityFeed" class="space-y-3 max-h-64 overflow-y-auto">
        <div class="text-center text-gray-500 py-4">
            <i class="fas fa-broadcast-tower text-2xl mb-2"></i>
            <p>Waiting for student activity...</p>
        </div>
    </div>
</div>

<style>
.top-three {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

.top-three:nth-child(1) {
    background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 100%);
    box-shadow: 0 4px 6px -1px rgba(251, 191, 36, 0.1);
}

.top-three:nth-child(2) {
    background: linear-gradient(135deg, #f3f4f6 0%, #d1d5db 100%);
    box-shadow: 0 4px 6px -1px rgba(156, 163, 175, 0.1);
}

.top-three:nth-child(3) {
    background: linear-gradient(135deg, #fed7aa 0%, #fb923c 100%);
    box-shadow: 0 4px 6px -1px rgba(251, 146, 60, 0.1);
}

.leaderboard-row {
    transition: all 0.3s ease;
}

.leaderboard-row:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.new-submission {
    animation: slideInFromRight 0.5s ease-out;
}

@keyframes slideInFromRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.rank-change {
    animation: rankChange 0.6s ease-in-out;
}

@keyframes rankChange {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.activity-item {
    animation: fadeInUp 0.4s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.live-indicator {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>

<script>
let liveMode = true;
let refreshInterval;
let lastUpdateTime = new Date();

// Initialize live updates
function initLiveUpdates() {
    if (liveMode) {
        refreshInterval = setInterval(updateLeaderboard, 3000); // Update every 3 seconds
        updateLeaderboard(); // Initial update
    }
}

// Toggle live mode
function toggleLiveMode() {
    liveMode = !liveMode;
    const btn = document.getElementById('liveModeBtn');

    if (liveMode) {
        btn.innerHTML = '<i class="fas fa-broadcast-tower mr-2"></i>Live Mode: ON';
        btn.className = 'bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center';
        initLiveUpdates();
    } else {
        btn.innerHTML = '<i class="fas fa-pause mr-2"></i>Live Mode: OFF';
        btn.className = 'bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center';
        clearInterval(refreshInterval);
    }
}

// Update leaderboard data
function updateLeaderboard() {
    fetch(`../api/get-quiz-leaderboard.php?quiz_id=<?php echo $quiz_id; ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateLeaderboardTable(data.leaderboard);
                updateStatistics(data.statistics);
                updateActivityFeed(data.recent_activity);
                lastUpdateTime = new Date();
                updateLastUpdated();
            }
        })
        .catch(error => {
            console.error('Error updating leaderboard:', error);
        });
}

// Update leaderboard table
function updateLeaderboardTable(leaderboard) {
    const tbody = document.getElementById('leaderboardBody');

    if (leaderboard.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-trophy text-4xl mb-4 text-gray-300"></i>
                    <p>No submissions yet. Students will appear here as they complete the quiz.</p>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    leaderboard.forEach((student, index) => {
        const isTopThree = index < 3;
        const rankClass = isTopThree ? 'top-three' : '';
        const rankHtml = isTopThree ?
            `<div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm
                ${index === 0 ? 'bg-yellow-500' : (index === 1 ? 'bg-gray-400' : 'bg-orange-500')}">${student.rank}</div>` :
            `<span class="text-gray-900 font-medium">${student.rank}</span>`;

        html += `
            <tr class="leaderboard-row ${rankClass}" data-rank="${student.rank}">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        ${rankHtml}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                            <span class="text-white font-medium text-sm">
                                ${student.first_name.charAt(0).toUpperCase() + student.last_name.charAt(0).toUpperCase()}
                            </span>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                ${student.first_name} ${student.last_name}
                            </div>
                            <div class="text-sm text-gray-500">${student.student_id}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-lg font-bold text-gray-900">${student.score}</div>
                    <div class="text-sm text-gray-500">${student.correct_answers}/${student.total_questions} correct</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-green-500 h-2 rounded-full transition-all duration-500"
                                 style="width: ${student.percentage}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900">${student.percentage}%</span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${formatTime(student.time_taken)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${formatDateTime(student.completed_at)}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// Update statistics
function updateStatistics(stats) {
    document.getElementById('totalSubmissions').textContent = stats.total_submissions;
    document.getElementById('completedSubmissions').textContent = stats.completed_submissions;
    document.getElementById('highestScore').textContent = stats.highest_score;
    document.getElementById('fastestTime').textContent = stats.fastest_time ? formatTime(stats.fastest_time) : 'N/A';
}

// Update activity feed
function updateActivityFeed(activities) {
    const feed = document.getElementById('activityFeed');

    if (activities.length === 0) {
        feed.innerHTML = `
            <div class="text-center text-gray-500 py-4">
                <i class="fas fa-broadcast-tower text-2xl mb-2"></i>
                <p>Waiting for student activity...</p>
            </div>
        `;
        return;
    }

    let html = '';
    activities.forEach(activity => {
        const timeAgo = getTimeAgo(activity.timestamp);
        html += `
            <div class="activity-item flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                <div class="w-2 h-2 bg-green-500 rounded-full live-indicator"></div>
                <div class="flex-1">
                    <p class="text-sm text-gray-900">
                        <span class="font-medium">${activity.student_name}</span>
                        ${activity.action}
                    </p>
                    <p class="text-xs text-gray-500">${timeAgo}</p>
                </div>
            </div>
        `;
    });

    feed.innerHTML = html;
}

// Update last updated timestamp
function updateLastUpdated() {
    const element = document.getElementById('lastUpdated');
    element.textContent = `Last updated: ${lastUpdateTime.toLocaleTimeString()}`;
}

// Utility functions
function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diffInSeconds = Math.floor((now - time) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    return `${Math.floor(diffInSeconds / 86400)}d ago`;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initLiveUpdates();

    // Add confetti effect for top 3 positions
    const topThreeRows = document.querySelectorAll('.top-three');
    topThreeRows.forEach((row, index) => {
        if (index === 0) { // Gold medal
            row.addEventListener('mouseenter', () => {
                createConfetti('#fbbf24');
            });
        }
    });
});

// Confetti effect
function createConfetti(color) {
    const confettiCount = 50;
    const confetti = [];

    for (let i = 0; i < confettiCount; i++) {
        const confettiPiece = document.createElement('div');
        confettiPiece.style.position = 'fixed';
        confettiPiece.style.left = Math.random() * 100 + 'vw';
        confettiPiece.style.top = '-10px';
        confettiPiece.style.width = '10px';
        confettiPiece.style.height = '10px';
        confettiPiece.style.backgroundColor = color;
        confettiPiece.style.borderRadius = '50%';
        confettiPiece.style.pointerEvents = 'none';
        confettiPiece.style.zIndex = '9999';
        confettiPiece.style.animation = `fall ${Math.random() * 3 + 2}s linear forwards`;

        document.body.appendChild(confettiPiece);
        confetti.push(confettiPiece);

        setTimeout(() => {
            document.body.removeChild(confettiPiece);
        }, 5000);
    }
}

// Add fall animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fall {
        to {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'includes/footer.php'; ?>