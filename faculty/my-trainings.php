<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'My Trainings & Seminars';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get faculty's registered trainings
$registered_trainings_query = "SELECT ts.*, tr.registration_date, tr.status as registration_status,
                              tc.name as category_name,
                              mec.name as main_category_name,
                              esc.name as sub_category_name
                              FROM training_registrations tr
                              JOIN trainings_seminars ts ON tr.training_id = ts.id
                              LEFT JOIN training_categories tc ON ts.category_id = tc.id
                              LEFT JOIN main_evaluation_categories mec ON ts.main_category_id = mec.id
                              LEFT JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
                              WHERE tr.user_id = ? AND ts.status = 'published'
                              ORDER BY ts.start_date ASC";

$stmt = mysqli_prepare($conn, $registered_trainings_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$registered_trainings_result = mysqli_stmt_get_result($stmt);

// Get available trainings (not registered)
$available_trainings_query = "SELECT ts.*, tc.name as category_name,
                             mec.name as main_category_name,
                             esc.name as sub_category_name
                             FROM trainings_seminars ts
                             LEFT JOIN training_categories tc ON ts.category_id = tc.id
                             LEFT JOIN main_evaluation_categories mec ON ts.main_category_id = mec.id
                             LEFT JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
                             WHERE ts.status = 'published'
                             AND ts.start_date > NOW()
                             AND ts.id NOT IN (
                                 SELECT training_id FROM training_registrations WHERE user_id = ?
                             )
                             ORDER BY ts.start_date ASC";

$stmt = mysqli_prepare($conn, $available_trainings_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$available_trainings_result = mysqli_stmt_get_result($stmt);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">My Trainings & Seminars</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Manage your training registrations and discover new opportunities
            </p>
        </div>
        <div class="flex space-x-2">
            <a href="dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-user-check text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Registered Trainings</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo mysqli_num_rows($registered_trainings_result); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-graduation-cap text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Available Trainings</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo mysqli_num_rows($available_trainings_result); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <i class="fas fa-certificate text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Completed This Year</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php
                    $completed_query = "SELECT COUNT(*) as count FROM training_registrations tr
                                      JOIN trainings_seminars ts ON tr.training_id = ts.id
                                      WHERE tr.user_id = ? AND ts.end_date < NOW()
                                      AND YEAR(ts.end_date) = YEAR(NOW())";
                    $stmt = mysqli_prepare($conn, $completed_query);
                    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
                    mysqli_stmt_execute($stmt);
                    $completed_result = mysqli_stmt_get_result($stmt);
                    echo mysqli_fetch_assoc($completed_result)['count'];
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
            <button onclick="showTab('registered')"
                    class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-seait-orange text-seait-orange"
                    id="tab-button-registered">
                <div class="flex items-center">
                    <i class="fas fa-user-check mr-2"></i>
                    <span>My Registered Trainings</span>
                    <span class="ml-2 bg-seait-orange text-white text-xs rounded-full px-2 py-1">
                        <?php echo mysqli_num_rows($registered_trainings_result); ?>
                    </span>
                </div>
            </button>
            <button onclick="showTab('available')"
                    class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                    id="tab-button-available">
                <div class="flex items-center">
                    <i class="fas fa-graduation-cap mr-2"></i>
                    <span>Available Trainings</span>
                    <span class="ml-2 bg-green-500 text-white text-xs rounded-full px-2 py-1">
                        <?php echo mysqli_num_rows($available_trainings_result); ?>
                    </span>
                </div>
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="p-6">
        <!-- Registered Trainings Tab -->
        <div class="tab-content block" id="tab-content-registered">
            <?php if (mysqli_num_rows($registered_trainings_result) > 0): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php while ($training = mysqli_fetch_assoc($registered_trainings_result)): ?>
                        <?php
                        $training_started = strtotime($training['start_date']) < time();
                        $training_ended = strtotime($training['end_date']) < time();
                        $registration_deadline_passed = strtotime($training['registration_deadline']) < time();
                        ?>
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                            <?php echo htmlspecialchars($training['title']); ?>
                                        </h3>
                                        <div class="flex items-center space-x-2 mb-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-tag mr-1"></i>
                                                <?php echo ucfirst($training['type']); ?>
                                            </span>
                                            <?php if ($training['is_mandatory']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    Mandatory
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($training_ended): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-1"></i>
                                                    Completed
                                                </span>
                                            <?php elseif ($training_started): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-play mr-1"></i>
                                                    In Progress
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    Upcoming
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3 mb-4">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-calendar-alt text-gray-400 mr-2 w-4"></i>
                                        <span><?php echo date('M d, Y', strtotime($training['start_date'])); ?> - <?php echo date('M d, Y', strtotime($training['end_date'])); ?></span>
                                    </div>
                                    <?php if ($training['venue']): ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-2 w-4"></i>
                                        <span><?php echo htmlspecialchars($training['venue']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($training['duration_hours']): ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-clock text-gray-400 mr-2 w-4"></i>
                                        <span><?php echo $training['duration_hours']; ?> hours</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-user-plus text-gray-400 mr-2 w-4"></i>
                                        <span>Registered on <?php echo date('M d, Y', strtotime($training['registration_date'])); ?></span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-600">
                                        <?php if ($training['cost'] > 0): ?>
                                            <span class="font-medium">₱<?php echo number_format($training['cost'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-green-600 font-medium">FREE</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="view-training.php?id=<?php echo $training['id']; ?>"
                                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-seait-orange bg-orange-50 border border-orange-200 rounded-lg hover:bg-orange-100 transition">
                                        <i class="fas fa-eye mr-2"></i>
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-graduation-cap text-gray-400 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Registered Trainings</h3>
                    <p class="text-gray-500 mb-6">You haven't registered for any trainings yet.</p>
                    <button onclick="showTab('available')" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas fa-search mr-2"></i>Browse Available Trainings
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Available Trainings Tab -->
        <div class="tab-content hidden" id="tab-content-available">
            <?php if (mysqli_num_rows($available_trainings_result) > 0): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php while ($training = mysqli_fetch_assoc($available_trainings_result)): ?>
                        <?php
                        $registration_deadline_passed = strtotime($training['registration_deadline']) < time();
                        $training_started = strtotime($training['start_date']) < time();

                        // Get participants count
                        $participants_count_query = "SELECT COUNT(*) as count FROM training_registrations
                                                   WHERE training_id = ? AND status = 'registered'";
                        $stmt = mysqli_prepare($conn, $participants_count_query);
                        mysqli_stmt_bind_param($stmt, "i", $training['id']);
                        mysqli_stmt_execute($stmt);
                        $participants_count_result = mysqli_stmt_get_result($stmt);
                        $participants_count = mysqli_fetch_assoc($participants_count_result)['count'];
                        $is_full = $training['max_participants'] && $participants_count >= $training['max_participants'];
                        ?>
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                            <?php echo htmlspecialchars($training['title']); ?>
                                        </h3>
                                        <div class="flex items-center space-x-2 mb-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-tag mr-1"></i>
                                                <?php echo ucfirst($training['type']); ?>
                                            </span>
                                            <?php if ($training['is_mandatory']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    Mandatory
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($registration_deadline_passed): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-times mr-1"></i>
                                                    Registration Closed
                                                </span>
                                            <?php elseif ($is_full): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-users mr-1"></i>
                                                    Full
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-1"></i>
                                                    Open for Registration
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3 mb-4">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-calendar-alt text-gray-400 mr-2 w-4"></i>
                                        <span><?php echo date('M d, Y', strtotime($training['start_date'])); ?> - <?php echo date('M d, Y', strtotime($training['end_date'])); ?></span>
                                    </div>
                                    <?php if ($training['venue']): ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-2 w-4"></i>
                                        <span><?php echo htmlspecialchars($training['venue']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($training['duration_hours']): ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-clock text-gray-400 mr-2 w-4"></i>
                                        <span><?php echo $training['duration_hours']; ?> hours</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-users text-gray-400 mr-2 w-4"></i>
                                        <span><?php echo $participants_count; ?>/<?php echo $training['max_participants'] ?? '∞'; ?> participants</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-600">
                                        <?php if ($training['cost'] > 0): ?>
                                            <span class="font-medium">₱<?php echo number_format($training['cost'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-green-600 font-medium">FREE</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="view-training.php?id=<?php echo $training['id']; ?>"
                                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-seait-orange bg-orange-50 border border-orange-200 rounded-lg hover:bg-orange-100 transition">
                                        <i class="fas fa-eye mr-2"></i>
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-search text-gray-400 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Available Trainings</h3>
                    <p class="text-gray-500">There are currently no available trainings for registration.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('block');
    });

    // Remove active state from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('border-seait-orange', 'text-seait-orange');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    // Show selected tab content
    const selectedContent = document.getElementById('tab-content-' + tabName);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('block');
    }

    // Add active state to selected tab button
    const selectedButton = document.getElementById('tab-button-' + tabName);
    if (selectedButton) {
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
        selectedButton.classList.add('border-seait-orange', 'text-seait-orange');
    }
}
</script>

<style>
.tab-button {
    transition: all 0.2s ease-in-out;
}

.tab-button:hover {
    background-color: rgba(249, 115, 22, 0.05);
}

.tab-content {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php
// Include the shared footer
include 'includes/footer.php';
?>