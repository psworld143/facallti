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
$page_title = 'Training & Seminar Suggestions';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get faculty member details by email
$faculty_query = "SELECT f.id as faculty_id, f.first_name, f.last_name, f.email, 'teacher' as role,
                        f.department, f.position, f.is_active as status
                 FROM faculty f
                 WHERE f.email = ? AND f.is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $_SESSION['email']);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty = mysqli_fetch_assoc($faculty_result);

if (!$faculty) {
    $_SESSION['message'] = 'Faculty member not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

// Get evaluation categories and their statistics for this faculty member
$categories_query = "SELECT
    mec.id as category_id,
    mec.name as category_name,
    mec.evaluation_type,
    mec.description as category_description,
    COUNT(DISTINCT es.id) as total_evaluations,
    AVG(er.rating_value) as average_rating,
    MIN(er.rating_value) as min_rating,
    MAX(er.rating_value) as max_rating,
    STDDEV(er.rating_value) as rating_stddev
FROM main_evaluation_categories mec
LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id
    AND es.evaluatee_id IN (SELECT id FROM users WHERE email = ?)
    AND es.status = 'completed'
LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
GROUP BY mec.id, mec.name, mec.evaluation_type, mec.description
ORDER BY mec.evaluation_type, mec.name";
$categories_stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($categories_stmt, "s", $faculty['email']);
mysqli_stmt_execute($categories_stmt);
$categories_result = mysqli_stmt_get_result($categories_stmt);

$categories = [];
while ($category = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $category;
}

// Calculate overall statistics
$overall_stats_query = "SELECT
    COUNT(DISTINCT es.id) as total_evaluations,
    AVG(er.rating_value) as overall_average,
    MIN(er.rating_value) as overall_min,
    MAX(er.rating_value) as overall_max,
    STDDEV(er.rating_value) as overall_stddev,
    COUNT(DISTINCT es.semester_id) as total_semesters,
    COUNT(DISTINCT es.main_category_id) as total_categories
FROM evaluation_sessions es
JOIN users u ON es.evaluatee_id = u.id
LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
WHERE u.email = ? AND es.status = 'completed'";
$overall_stats_stmt = mysqli_prepare($conn, $overall_stats_query);
mysqli_stmt_bind_param($overall_stats_stmt, "s", $faculty['email']);
mysqli_stmt_execute($overall_stats_stmt);
$overall_stats_result = mysqli_stmt_get_result($overall_stats_stmt);
$overall_stats = mysqli_fetch_assoc($overall_stats_result);

// If no evaluation data exists, set default values
if (!$overall_stats || $overall_stats['total_evaluations'] == 0) {
    $overall_stats = [
        'total_evaluations' => 0,
        'overall_average' => 0,
        'overall_min' => 0,
        'overall_max' => 0,
        'overall_stddev' => 0,
        'total_semesters' => 0,
        'total_categories' => 0
    ];
}

// Get training and seminar suggestions for categories with scores below 4.0
$training_suggestions = [];
if ($overall_stats['total_evaluations'] > 0) {
    // Get categories with scores below 4.0
    $low_performing_categories = array_filter($categories, function($cat) {
        return ($cat['average_rating'] ?? 0) < 4.0;
    });

    if (!empty($low_performing_categories)) {
        foreach ($low_performing_categories as $category) {
            // Get trainings and seminars for this category
            $trainings_query = "SELECT ts.*,
                               tc.name as category_name,
                               mec.name as main_category_name,
                               esc.name as sub_category_name,
                               u.first_name, u.last_name
                               FROM trainings_seminars ts
                               LEFT JOIN training_categories tc ON ts.category_id = tc.id
                               LEFT JOIN main_evaluation_categories mec ON ts.main_category_id = mec.id
                               LEFT JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
                               LEFT JOIN users u ON ts.created_by = u.id
                               WHERE (ts.main_category_id = ? OR ts.sub_category_id IN (
                                   SELECT id FROM evaluation_sub_categories WHERE main_category_id = ?
                               ))
                               ORDER BY ts.start_date DESC, ts.created_at DESC";

            $trainings_stmt = mysqli_prepare($conn, $trainings_query);
            mysqli_stmt_bind_param($trainings_stmt, "ii", $category['category_id'], $category['category_id']);
            mysqli_stmt_execute($trainings_stmt);
            $trainings_result = mysqli_stmt_get_result($trainings_stmt);

            $category_trainings = [];
            while ($training = mysqli_fetch_assoc($trainings_result)) {
                $category_trainings[] = $training;
            }

            if (!empty($category_trainings)) {
                $training_suggestions[] = [
                    'category' => $category,
                    'trainings' => $category_trainings
                ];
            }
        }
    }
}

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Training & Seminar Suggestions</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Personalized recommendations based on your evaluation results
            </p>
        </div>
        <div class="flex space-x-2">
            <a href="dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
            <button onclick="window.print()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-print mr-2"></i>Print Report
            </button>
        </div>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Faculty Information -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-seait-orange to-orange-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-user-tie mr-3"></i>Faculty Information
        </h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['email']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['department']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['position']); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- No Evaluation Data Alert -->
<?php if ($overall_stats['total_evaluations'] == 0): ?>
<div class="mb-6 p-6 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400 text-xl"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-lg font-medium text-blue-800">No Evaluation Data Available</h3>
            <div class="mt-2 text-sm text-blue-700">
                <p><strong>Evaluation Status:</strong> You currently have no completed evaluations in the system.</p>
                <p class="mt-1"><strong>Possible Reasons:</strong></p>
                <ul class="list-disc list-inside mt-1 ml-4">
                    <li>No evaluations have been conducted yet</li>
                    <li>All evaluations are still in draft status</li>
                    <li>Evaluation sessions haven't been created for you</li>
                </ul>
            </div>
            <div class="mt-3 p-3 bg-blue-100 rounded-md">
                <p class="text-sm font-medium text-blue-800">To see training suggestions:</p>
                <p class="text-sm text-blue-700 mt-1">• Wait for evaluations to be completed by students, peers, or department heads</p>
                <p class="text-sm text-blue-700">• Contact your department head or guidance office for evaluation status</p>
                <p class="text-sm text-blue-700">• Check back later for updated evaluation results</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Overall Statistics -->
<?php if ($overall_stats['total_evaluations'] > 0): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-chart-line text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Overall Average</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo number_format($overall_stats['overall_average'] ?? 0, 2); ?>/5.00
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-clipboard-check text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Evaluations</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['total_evaluations']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-calendar-alt text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Semesters Evaluated</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['total_semesters']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <i class="fas fa-star text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Rating Range</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo $overall_stats['overall_min']; ?> - <?php echo $overall_stats['overall_max']; ?>
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Training and Seminar Suggestions -->
<?php if (!empty($training_suggestions)): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-yellow-500 to-yellow-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-lightbulb mr-3"></i>Training and Seminar Suggestions
        </h2>
        <p class="text-yellow-100 text-sm mt-1">Based on evaluation scores below 4.0</p>
    </div>
    <div class="p-6">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
                <?php foreach ($training_suggestions as $index => $suggestion): ?>
                    <button onclick="showTrainingTab(<?php echo $index; ?>)"
                            class="training-tab-btn whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 <?php echo $index === 0 ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                            id="tab-btn-<?php echo $index; ?>">
                        <i class="fas fa-chalkboard-teacher mr-2"></i>
                        <?php echo htmlspecialchars($suggestion['category']['category_name']); ?>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                            <?php echo number_format($suggestion['category']['average_rating'] ?? 0, 2); ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="space-y-6">
            <?php foreach ($training_suggestions as $index => $suggestion): ?>
                <div class="training-tab-content <?php echo $index === 0 ? 'block' : 'hidden'; ?>"
                     id="tab-content-<?php echo $index; ?>">

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-yellow-800">
                                <?php echo htmlspecialchars($suggestion['category']['category_name']); ?>
                            </h3>
                            <span class="px-3 py-1 bg-red-100 text-red-800 text-sm font-medium rounded-full">
                                Score: <?php echo number_format($suggestion['category']['average_rating'] ?? 0, 2); ?>/5.00
                            </span>
                        </div>

                        <div class="mb-4">
                            <p class="text-sm text-yellow-700 mb-2">
                                <strong>Evaluation Type:</strong>
                                <span class="capitalize"><?php echo str_replace('_', ' ', $suggestion['category']['evaluation_type']); ?></span>
                            </p>
                            <p class="text-sm text-yellow-700">
                                <strong>Total Evaluations:</strong> <?php echo $suggestion['category']['total_evaluations']; ?>
                            </p>
                        </div>

                        <div class="border-t border-yellow-200 pt-4">
                            <h4 class="font-medium text-yellow-800 mb-3">Available Training Programs & Seminars:</h4>
                            <?php if (!empty($suggestion['trainings'])): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($suggestion['trainings'] as $training): ?>
                                        <div class="bg-white border border-yellow-300 rounded-lg p-4 hover:shadow-md transition-shadow">
                                            <div class="flex items-start justify-between mb-2">
                                                <span class="px-2 py-1 text-xs rounded-full
                                                    <?php
                                                    switch($training['type']) {
                                                        case 'training':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'seminar':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'workshop':
                                                            echo 'bg-purple-100 text-purple-800';
                                                            break;
                                                        case 'conference':
                                                            echo 'bg-orange-100 text-orange-800';
                                                            break;
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($training['type']); ?>
                                                </span>
                                                <span class="px-2 py-1 text-xs rounded-full
                                                    <?php
                                                    switch($training['status']) {
                                                        case 'published':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'draft':
                                                            echo 'bg-gray-100 text-gray-800';
                                                            break;
                                                        case 'ongoing':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'completed':
                                                            echo 'bg-purple-100 text-purple-800';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-red-100 text-red-800';
                                                            break;
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($training['status']); ?>
                                                </span>
                                            </div>

                                            <!-- Category Information -->
                                            <div class="mb-3">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-tag mr-2 text-gray-500"></i>
                                                    <span class="text-xs font-medium text-gray-700">Category:</span>
                                                </div>
                                                <div class="space-y-1">
                                                    <?php if ($training['main_category_name']): ?>
                                                        <div class="flex items-center">
                                                            <span class="px-2 py-1 text-xs rounded-full bg-indigo-100 text-indigo-800 mr-2">
                                                                <i class="fas fa-layer-group mr-1"></i>Main
                                                            </span>
                                                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($training['main_category_name']); ?></span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($training['sub_category_name']): ?>
                                                        <div class="flex items-center">
                                                            <span class="px-2 py-1 text-xs rounded-full bg-teal-100 text-teal-800 mr-2">
                                                                <i class="fas fa-tags mr-1"></i>Sub
                                                            </span>
                                                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($training['sub_category_name']); ?></span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($training['category_name']): ?>
                                                        <div class="flex items-center">
                                                            <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800 mr-2">
                                                                <i class="fas fa-folder mr-1"></i>Training
                                                            </span>
                                                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($training['category_name']); ?></span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!$training['main_category_name'] && !$training['sub_category_name'] && !$training['category_name']): ?>
                                                        <div class="flex items-center">
                                                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800 mr-2">
                                                                <i class="fas fa-info-circle mr-1"></i>General
                                                            </span>
                                                            <span class="text-sm text-gray-500">No specific category assigned</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <h5 class="font-medium text-gray-900 mb-2 line-clamp-2">
                                                <?php echo htmlspecialchars($training['title']); ?>
                                            </h5>

                                            <?php if ($training['description']): ?>
                                                <p class="text-sm text-gray-600 mb-3 line-clamp-3">
                                                    <?php echo htmlspecialchars(substr($training['description'], 0, 100)); ?>
                                                    <?php if (strlen($training['description']) > 100): ?>...<?php endif; ?>
                                                </p>
                                            <?php endif; ?>

                                            <div class="space-y-1 text-xs text-gray-600">
                                                <?php if ($training['start_date']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-calendar-alt mr-2 text-yellow-600"></i>
                                                        <span><?php echo date('M d, Y', strtotime($training['start_date'])); ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($training['venue']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-map-marker-alt mr-2 text-yellow-600"></i>
                                                        <span><?php echo htmlspecialchars($training['venue']); ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($training['duration_hours']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-clock mr-2 text-yellow-600"></i>
                                                        <span><?php echo $training['duration_hours']; ?> hours</span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($training['first_name'] && $training['last_name']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-user mr-2 text-yellow-600"></i>
                                                        <span><?php echo htmlspecialchars($training['first_name'] . ' ' . $training['last_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mt-4 flex space-x-2">
                                                <a href="../IntelliEVal/view-training.php?id=<?php echo $training['id']; ?>"
                                                   class="flex-1 bg-yellow-600 text-white text-center py-2 px-3 rounded text-sm hover:bg-yellow-700 transition-colors">
                                                    <i class="fas fa-eye mr-1"></i>View Details
                                                </a>
                                                <?php if ($training['status'] === 'published'): ?>
                                                    <a href="../IntelliEVal/trainings.php?category=<?php echo $training['category_id']; ?>"
                                                       class="flex-1 bg-blue-600 text-white text-center py-2 px-3 rounded text-sm hover:bg-blue-700 transition-colors">
                                                        <i class="fas fa-plus mr-1"></i>Register
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                                    <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                                    <p class="text-gray-600">No specific training programs or seminars are currently available for this category.</p>
                                    <p class="text-sm text-gray-500 mt-1">Check back later for new opportunities or contact the guidance office.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                <div>
                    <h4 class="font-medium text-blue-800 mb-2">About Training Suggestions</h4>
                    <p class="text-sm text-blue-700 mb-2">
                        These suggestions are based on evaluation scores below 4.0 in specific categories.
                        Training programs and seminars are recommended to help improve performance in these areas.
                    </p>
                    <p class="text-sm text-blue-700">
                        <strong>Note:</strong> All available training programs and seminars are shown regardless of their scheduled status.
                        Contact the guidance office for registration and scheduling information.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Tab Functionality -->
<script>
function showTrainingTab(tabIndex) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.training-tab-content');
    tabContents.forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active state from all tab buttons
    const tabButtons = document.querySelectorAll('.training-tab-btn');
    tabButtons.forEach(btn => {
        btn.classList.remove('border-yellow-500', 'text-yellow-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });

    // Show selected tab content
    const selectedContent = document.getElementById('tab-content-' + tabIndex);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('block');
    }

    // Add active state to selected tab button
    const selectedButton = document.getElementById('tab-btn-' + tabIndex);
    if (selectedButton) {
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
        selectedButton.classList.add('border-yellow-500', 'text-yellow-600');
    }
}
</script>
<?php endif; ?>

<?php
// Include the shared footer
include 'includes/unified-footer.php';
?>