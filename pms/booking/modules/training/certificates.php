<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch certificates and achievements data
try {
    // Get earned certificates
    $stmt = $pdo->prepare("
        SELECT 
            tc.*,
            CASE 
                WHEN tc.scenario_type = 'scenario' THEN ts.title
                WHEN tc.scenario_type = 'customer_service' THEN css.title
                WHEN tc.scenario_type = 'problem_solving' THEN ps.title
                ELSE 'Unknown Scenario'
            END as scenario_title,
            tc.scenario_type,
            ta.score,
            ta.completed_at
        FROM training_certificates tc
        LEFT JOIN training_attempts ta ON tc.attempt_id = ta.id
        LEFT JOIN training_scenarios ts ON ta.scenario_id = ts.id AND ta.scenario_type = 'scenario'
        LEFT JOIN customer_service_scenarios css ON ta.scenario_id = css.id AND ta.scenario_type = 'customer_service'
        LEFT JOIN problem_scenarios ps ON ta.scenario_id = ps.id AND ta.scenario_type = 'problem_solving'
        WHERE tc.user_id = ?
        ORDER BY tc.issued_date DESC
    ");
    $stmt->execute([$user_id]);
    $certificates = $stmt->fetchAll();

    // Get achievement badges
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN score >= 95 THEN 1 END) as perfect_scores,
            COUNT(CASE WHEN score >= 90 AND score < 95 THEN 1 END) as excellent_scores,
            COUNT(CASE WHEN score >= 80 AND score < 90 THEN 1 END) as good_scores,
            COUNT(CASE WHEN scenario_type = 'scenario' THEN 1 END) as training_scenarios_completed,
            COUNT(CASE WHEN scenario_type = 'customer_service' THEN 1 END) as customer_service_completed,
            COUNT(CASE WHEN scenario_type = 'problem_solving' THEN 1 END) as problem_solving_completed,
            COUNT(CASE WHEN duration_minutes <= 5 THEN 1 END) as speed_learner,
            COUNT(CASE WHEN duration_minutes >= 30 THEN 1 END) as thorough_learner
        FROM training_attempts 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $achievements = $stmt->fetch();

    // Get certificate statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_certificates,
            COUNT(CASE WHEN certificate_type = 'completion' THEN 1 END) as completion_certificates,
            COUNT(CASE WHEN certificate_type = 'achievement' THEN 1 END) as achievement_certificates,
            COUNT(CASE WHEN certificate_type = 'mastery' THEN 1 END) as mastery_certificates
        FROM training_certificates 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cert_stats = $stmt->fetch();

    // Get recent achievements
    $stmt = $pdo->prepare("
        SELECT 
            ta.*,
            CASE 
                WHEN ta.scenario_type = 'scenario' THEN ts.title
                WHEN ta.scenario_type = 'customer_service' THEN css.title
                WHEN ta.scenario_type = 'problem_solving' THEN ps.title
                ELSE 'Unknown Scenario'
            END as scenario_title,
            ta.scenario_type
        FROM training_attempts ta
        LEFT JOIN training_scenarios ts ON ta.scenario_id = ts.id AND ta.scenario_type = 'scenario'
        LEFT JOIN customer_service_scenarios css ON ta.scenario_id = css.id AND ta.scenario_type = 'customer_service'
        LEFT JOIN problem_scenarios ps ON ta.scenario_id = ps.id AND ta.scenario_type = 'problem_solving'
        WHERE ta.user_id = ? AND ta.status = 'completed' AND ta.score >= 80
        ORDER BY ta.completed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_achievements = $stmt->fetchAll();

    // Get learning milestones
    $stmt = $pdo->prepare("
        SELECT 
            'First Completion' as milestone,
            MIN(completed_at) as achieved_date,
            'Completed your first training scenario' as description
        FROM training_attempts 
        WHERE user_id = ? AND status = 'completed'
        UNION ALL
        SELECT 
            'Perfect Score' as milestone,
            MIN(completed_at) as achieved_date,
            'Achieved your first perfect score (100%)' as description
        FROM training_attempts 
        WHERE user_id = ? AND status = 'completed' AND score = 100
        UNION ALL
        SELECT 
            'Speed Learner' as milestone,
            MIN(completed_at) as achieved_date,
            'Completed a scenario in under 5 minutes' as description
        FROM training_attempts 
        WHERE user_id = ? AND status = 'completed' AND duration_minutes <= 5
        UNION ALL
        SELECT 
            'Consistent Performer' as milestone,
            MIN(completed_at) as achieved_date,
            'Achieved 5 consecutive scores above 80%' as description
        FROM training_attempts 
        WHERE user_id = ? AND status = 'completed' AND score >= 80
        HAVING COUNT(*) >= 5
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $milestones = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching certificates: " . $e->getMessage());
    $certificates = [];
    $achievements = [
        'perfect_scores' => 0,
        'excellent_scores' => 0,
        'good_scores' => 0,
        'training_scenarios_completed' => 0,
        'customer_service_completed' => 0,
        'problem_solving_completed' => 0,
        'speed_learner' => 0,
        'thorough_learner' => 0
    ];
    $cert_stats = [
        'total_certificates' => 0,
        'completion_certificates' => 0,
        'achievement_certificates' => 0,
        'mastery_certificates' => 0
    ];
    $recent_achievements = [];
    $milestones = [];
}

// Set page title for unified header
$page_title = 'Training Certificates';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Training Certificates</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Your Achievements</h2>
                        <p class="text-gray-600 mt-1">View and download your earned certificates and achievement badges</p>
                    </div>
                    <div class="flex space-x-3">
                        <button class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Download All
                        </button>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-share mr-2"></i>Share Profile
                        </button>
                    </div>
                </div>
            </div>

            <!-- Certificate Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-certificate text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Certificates</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $cert_stats['total_certificates']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Completion</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $cert_stats['completion_certificates']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-trophy text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Achievements</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $cert_stats['achievement_certificates']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <i class="fas fa-crown text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Mastery</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $cert_stats['mastery_certificates']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Achievement Badges -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Achievement Badges</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
                    <!-- Perfect Scores Badge -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-2 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center <?php echo $achievements['perfect_scores'] > 0 ? 'opacity-100' : 'opacity-30'; ?>">
                            <i class="fas fa-trophy text-white text-xl"></i>
                        </div>
                        <p class="text-xs font-medium text-gray-800">Perfect Scores</p>
                        <p class="text-xs text-gray-500"><?php echo $achievements['perfect_scores']; ?></p>
                    </div>

                    <!-- Excellent Performance Badge -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-2 bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-center <?php echo $achievements['excellent_scores'] > 0 ? 'opacity-100' : 'opacity-30'; ?>">
                            <i class="fas fa-medal text-white text-xl"></i>
                        </div>
                        <p class="text-xs font-medium text-gray-800">Excellent</p>
                        <p class="text-xs text-gray-500"><?php echo $achievements['excellent_scores']; ?></p>
                    </div>

                    <!-- Good Performance Badge -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-2 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center <?php echo $achievements['good_scores'] > 0 ? 'opacity-100' : 'opacity-30'; ?>">
                            <i class="fas fa-star text-white text-xl"></i>
                        </div>
                        <p class="text-xs font-medium text-gray-800">Good</p>
                        <p class="text-xs text-gray-500"><?php echo $achievements['good_scores']; ?></p>
                    </div>

                    <!-- Training Scenarios Badge -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-2 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full flex items-center justify-center <?php echo $achievements['training_scenarios_completed'] > 0 ? 'opacity-100' : 'opacity-30'; ?>">
                            <i class="fas fa-play-circle text-white text-xl"></i>
                        </div>
                        <p class="text-xs font-medium text-gray-800">Training</p>
                        <p class="text-xs text-gray-500"><?php echo $achievements['training_scenarios_completed']; ?></p>
                    </div>

                    <!-- Customer Service Badge -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-2 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center <?php echo $achievements['customer_service_completed'] > 0 ? 'opacity-100' : 'opacity-30'; ?>">
                            <i class="fas fa-headset text-white text-xl"></i>
                        </div>
                        <p class="text-xs font-medium text-gray-800">Service</p>
                        <p class="text-xs text-gray-500"><?php echo $achievements['customer_service_completed']; ?></p>
                    </div>

                    <!-- Problem Solving Badge -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-2 bg-gradient-to-r from-orange-400 to-orange-600 rounded-full flex items-center justify-center <?php echo $achievements['problem_solving_completed'] > 0 ? 'opacity-100' : 'opacity-30'; ?>">
                            <i class="fas fa-puzzle-piece text-white text-xl"></i>
                        </div>
                        <p class="text-xs font-medium text-gray-800">Problem Solving</p>
                        <p class="text-xs text-gray-500"><?php echo $achievements['problem_solving_completed']; ?></p>
                    </div>

                    <!-- Speed Learner Badge -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-2 bg-gradient-to-r from-red-400 to-red-600 rounded-full flex items-center justify-center <?php echo $achievements['speed_learner'] > 0 ? 'opacity-100' : 'opacity-30'; ?>">
                            <i class="fas fa-bolt text-white text-xl"></i>
                        </div>
                        <p class="text-xs font-medium text-gray-800">Speed Learner</p>
                        <p class="text-xs text-gray-500"><?php echo $achievements['speed_learner']; ?></p>
                    </div>

                    <!-- Thorough Learner Badge -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-2 bg-gradient-to-r from-indigo-400 to-indigo-600 rounded-full flex items-center justify-center <?php echo $achievements['thorough_learner'] > 0 ? 'opacity-100' : 'opacity-30'; ?>">
                            <i class="fas fa-book text-white text-xl"></i>
                        </div>
                        <p class="text-xs font-medium text-gray-800">Thorough</p>
                        <p class="text-xs text-gray-500"><?php echo $achievements['thorough_learner']; ?></p>
                    </div>
                </div>
            </div>

            <!-- Certificates Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Earned Certificates -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Earned Certificates</h3>
                    <div class="space-y-4">
                        <?php if (empty($certificates)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-certificate text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-500">No certificates earned yet</p>
                                <p class="text-sm text-gray-400">Complete training scenarios to earn certificates</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center mr-4">
                                            <i class="fas fa-certificate text-white text-lg"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($cert['scenario_title']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($cert['issued_date'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo number_format($cert['score'], 1); ?>%
                                        </span>
                                        <button onclick="downloadCertificate(<?php echo $cert['id']; ?>)" class="ml-2 text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Achievements -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Achievements</h3>
                    <div class="space-y-3">
                        <?php if (empty($recent_achievements)): ?>
                            <p class="text-sm text-gray-500">No recent achievements</p>
                        <?php else: ?>
                            <?php foreach ($recent_achievements as $achievement): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-blue-600 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-trophy text-white text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($achievement['scenario_title']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($achievement['completed_at'])); ?></p>
                                        </div>
                                    </div>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        if ($achievement['score'] >= 95) echo 'bg-yellow-100 text-yellow-800';
                                        elseif ($achievement['score'] >= 90) echo 'bg-green-100 text-green-800';
                                        else echo 'bg-blue-100 text-blue-800';
                                        ?>">
                                        <?php echo number_format($achievement['score'], 1); ?>%
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Learning Milestones -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Learning Milestones</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php if (empty($milestones)): ?>
                        <div class="col-span-full text-center py-8">
                            <i class="fas fa-flag text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">No milestones achieved yet</p>
                            <p class="text-sm text-gray-400">Keep training to unlock milestones</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($milestones as $milestone): ?>
                            <div class="text-center p-4 bg-gradient-to-br from-purple-50 to-indigo-50 rounded-lg border border-purple-200">
                                <div class="w-16 h-16 mx-auto mb-3 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-flag text-white text-xl"></i>
                                </div>
                                <h4 class="font-semibold text-gray-800 mb-1"><?php echo $milestone['milestone']; ?></h4>
                                <p class="text-sm text-gray-600 mb-2"><?php echo $milestone['description']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($milestone['achieved_date'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Certificate Generation -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Generate Certificates</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button onclick="generateCompletionCertificate()" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                        <i class="fas fa-certificate text-purple-600 text-xl mr-3"></i>
                        <div class="text-left">
                            <span class="font-medium text-purple-800">Completion Certificate</span>
                            <p class="text-sm text-purple-600">Generate overall completion certificate</p>
                        </div>
                    </button>
                    <button onclick="generateAchievementCertificate()" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                        <i class="fas fa-trophy text-green-600 text-xl mr-3"></i>
                        <div class="text-left">
                            <span class="font-medium text-green-800">Achievement Certificate</span>
                            <p class="text-sm text-green-600">Generate achievement summary</p>
                        </div>
                    </button>
                    <button onclick="generateProgressReport()" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                        <i class="fas fa-chart-line text-blue-600 text-xl mr-3"></i>
                        <div class="text-left">
                            <span class="font-medium text-blue-800">Progress Report</span>
                            <p class="text-sm text-blue-600">Generate detailed progress report</p>
                        </div>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // Download certificate function
        function downloadCertificate(certificateId) {
            // Redirect to certificate download page
            window.location.href = `download-certificate.php?id=${certificateId}`;
        }

        // Generate completion certificate
        function generateCompletionCertificate() {
            if (confirm('Generate completion certificate?')) {
                window.location.href = 'generate-certificate.php?type=completion';
            }
        }

        // Generate achievement certificate
        function generateAchievementCertificate() {
            if (confirm('Generate achievement certificate?')) {
                window.location.href = 'generate-certificate.php?type=achievement';
            }
        }

        // Generate progress report
        function generateProgressReport() {
            if (confirm('Generate progress report?')) {
                window.location.href = 'generate-certificate.php?type=progress';
            }
        }

        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            
            $('#current-date').text(now.toLocaleDateString('en-US', dateOptions));
            $('#current-time').text(now.toLocaleTimeString('en-US', timeOptions));
        }

        // Update time every second
        setInterval(updateDateTime, 1000);
        updateDateTime();

        // Animate badges on page load
        $(document).ready(function() {
            $('.w-16.h-16').each(function(index) {
                $(this).delay(index * 100).animate({
                    opacity: $(this).hasClass('opacity-30') ? 0.3 : 1
                }, 500);
            });
        });
    </script>
</body>
</html>
