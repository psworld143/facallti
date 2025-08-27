<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Peer to Peer Evaluations';

$message = '';
$message_type = '';
$using_alt_query = false; // Flag to track if we're using alternative query

// Get teacher's department from faculty table
$faculty_query = "SELECT f.department FROM faculty f WHERE f.email = ? AND f.is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty_info = mysqli_fetch_assoc($faculty_result);

if (!$faculty_info) {
    $message = "Teacher profile not found. Please contact administrator. Username: " . $_SESSION['username'];
    $message_type = "error";
} else {
    $teacher_department = $faculty_info['department'];

    // Debug: Show current user info
    if (isset($_GET['debug'])) {
        $message = "Current user: " . $_SESSION['username'] . ", Department: " . $teacher_department . ", User ID: " . $_SESSION['user_id'];
        $message_type = "success";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start_evaluation':
                $evaluatee_id = (int)$_POST['evaluatee_id'];
                $main_category_id = (int)$_POST['main_category_id'];

                // Verify the evaluatee is from the same department
                // Since faculty are not associated with users, we work with faculty IDs directly
                $verify_query = "SELECT f.department FROM faculty f WHERE f.id = ? AND f.is_active = 1";
                $verify_stmt = mysqli_prepare($conn, $verify_query);
                mysqli_stmt_bind_param($verify_stmt, "i", $evaluatee_id);
                mysqli_stmt_execute($verify_stmt);
                $verify_result = mysqli_stmt_get_result($verify_stmt);
                $evaluatee_dept = mysqli_fetch_assoc($verify_result);

                // Debug: Show what we found
                if (isset($_GET['debug'])) {
                    $message = "Teacher department: '{$teacher_department}', Evaluatee ID: {$evaluatee_id}";
                    if ($evaluatee_dept) {
                        $message .= ", Evaluatee department: '{$evaluatee_dept['department']}'";
                    } else {
                        $message .= ", No evaluatee department found";

                        // Debug: Check what's in the faculty table
                        $debug_faculty_query = "SELECT f.id, f.first_name, f.last_name, f.email, f.department, f.is_active
                    FROM faculty f
                    WHERE f.department = ?";
                        $debug_stmt = mysqli_prepare($conn, $debug_faculty_query);
                        mysqli_stmt_bind_param($debug_stmt, "s", $teacher_department);
                        mysqli_stmt_execute($debug_stmt);
                        $debug_result = mysqli_stmt_get_result($debug_stmt);
                        $debug_count = mysqli_num_rows($debug_result);
                        $message .= ", Found {$debug_count} faculty in department";

                        // Debug: Check if the evaluatee_id exists in faculty table
                        $debug_check_query = "SELECT f.id, f.first_name, f.last_name, f.department FROM faculty f WHERE f.id = ?";
                        $debug_check_stmt = mysqli_prepare($conn, $debug_check_query);
                        mysqli_stmt_bind_param($debug_check_stmt, "i", $evaluatee_id);
                        mysqli_stmt_execute($debug_check_stmt);
                        $debug_check_result = mysqli_stmt_get_result($debug_check_stmt);
                        $debug_check_count = mysqli_num_rows($debug_check_result);
                        $message .= ", Faculty with ID {$evaluatee_id}: " . ($debug_check_count > 0 ? 'Found' : 'Not found');

                        if ($debug_check_count > 0) {
                            $debug_faculty_data = mysqli_fetch_assoc($debug_check_result);
                            $message .= " ({$debug_faculty_data['first_name']} {$debug_faculty_data['last_name']} - {$debug_faculty_data['department']})";
                        }
                    }
                    $message_type = "success";
                }

                if (!$evaluatee_dept || strtolower(trim($evaluatee_dept['department'])) !== strtolower(trim($teacher_department))) {
                    $message = "You can only evaluate faculty from your own department. Teacher dept: '{$teacher_department}', Evaluatee dept: '" . ($evaluatee_dept ? $evaluatee_dept['department'] : 'Not found') . "'";
                    $message_type = "error";
                } else {
                    // Check if evaluation already exists
                    $check_query = "SELECT id FROM evaluation_sessions WHERE evaluator_id = ? AND evaluatee_id = ? AND main_category_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "iii", $_SESSION['user_id'], $evaluatee_id, $main_category_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);

                    if (mysqli_num_rows($check_result) > 0) {
                        $message = "Evaluation already exists for this faculty member and category.";
                        $message_type = "error";
                    } else {
                        // Create new evaluation session
                        // Since faculty are not associated with users, we'll use faculty IDs directly
                        // We need to temporarily disable foreign key checks to insert faculty IDs

                        // Disable foreign key checks temporarily
                        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

                        $insert_query = "INSERT INTO evaluation_sessions (evaluator_id, evaluator_type, evaluatee_id, evaluatee_type, main_category_id, evaluation_date, status) VALUES (?, 'teacher', ?, 'teacher', ?, CURDATE(), 'draft')";
                        $insert_stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($insert_stmt, "iii", $_SESSION['user_id'], $evaluatee_id, $main_category_id);

                        if (mysqli_stmt_execute($insert_stmt)) {
                            $session_id = mysqli_insert_id($conn);

                            // Re-enable foreign key checks
                            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

                            header("Location: edit-peer-evaluation.php?session_id=" . $session_id);
                            exit();
                        } else {
                            // Re-enable foreign key checks
                            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

                            $message = "Error creating evaluation: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                }
                break;
        }
    }
}

// Get peer evaluation categories
$categories_query = "SELECT * FROM main_evaluation_categories WHERE evaluation_type = 'peer_to_peer' AND status = 'active' ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Get faculty from the same department (excluding self)
// Note: For peer evaluations, we use faculty IDs directly from the faculty table
// This is different from Head-to-Teacher evaluations which use users table for heads

// Handle department name variations more comprehensively
$department_conditions = [];
$params = [];
$param_types = "";

// Add exact match
$department_conditions[] = "f.department = ?";
$params[] = $teacher_department;
$param_types .= "s";

// Handle "Department of X" pattern
if (!str_contains($teacher_department, 'Department of ')) {
    $department_conditions[] = "f.department = ?";
    $params[] = 'Department of ' . $teacher_department;
    $param_types .= "s";
}

// Handle "X Department" pattern  
if (!str_contains($teacher_department, ' Department')) {
    $department_conditions[] = "f.department = ?";
    $params[] = $teacher_department . ' Department';
    $param_types .= "s";
}

// Handle "College of X" pattern
if (!str_contains($teacher_department, 'College of ')) {
    $department_conditions[] = "f.department = ?";
    $params[] = 'College of ' . $teacher_department;
    $param_types .= "s";
}

// Handle reverse patterns (if teacher has "College of X", check for just "X")
if (str_contains($teacher_department, 'College of ')) {
    $simple_name = str_replace('College of ', '', $teacher_department);
    $department_conditions[] = "f.department = ?";
    $params[] = $simple_name;
    $param_types .= "s";
    
    $department_conditions[] = "f.department = ?";
    $params[] = 'Department of ' . $simple_name;
    $param_types .= "s";
}

// Handle partial matches for complex department names
// If teacher has "College of Business and Good Governance", also check for "College of Business"
if (str_contains($teacher_department, ' and ')) {
    $parts = explode(' and ', $teacher_department);
    if (count($parts) >= 2) {
        $first_part = trim($parts[0]);
        $department_conditions[] = "f.department = ?";
        $params[] = $first_part;
        $param_types .= "s";
    }
}

// Handle "Information and Communication Technology" vs "Information Technology" variations
if (str_contains($teacher_department, 'Information and Communication Technology')) {
    $department_conditions[] = "f.department = ?";
    $params[] = 'College of Information Technology';
    $param_types .= "s";
    
    $department_conditions[] = "f.department = ?";
    $params[] = 'Department of Information Technology';
    $param_types .= "s";
}

$faculty_members_query = "SELECT f.id as faculty_id, f.first_name, f.last_name, f.email, f.position, f.id as user_id
                          FROM faculty f
                          WHERE (" . implode(' OR ', $department_conditions) . ") AND LOWER(f.email) != LOWER(?) AND f.is_active = 1
                          ORDER BY f.first_name, f.last_name ASC";
$faculty_members_stmt = mysqli_prepare($conn, $faculty_members_query);
$all_params = array_merge($params, [$_SESSION['username']]);
$all_param_types = $param_types . "s";
mysqli_stmt_bind_param($faculty_members_stmt, $all_param_types, ...$all_params);
mysqli_stmt_execute($faculty_members_stmt);
$faculty_members_result = mysqli_stmt_get_result($faculty_members_stmt);

// Debug: Check if we have faculty members
$faculty_count = mysqli_num_rows($faculty_members_result);

// Debug: Show what's in the dropdown
if (isset($_GET['debug'])) {
    $debug_dropdown = "Faculty members in dropdown:\n";
    mysqli_data_seek($faculty_members_result, 0);
    while ($faculty = mysqli_fetch_assoc($faculty_members_result)) {
        $debug_dropdown .= "- {$faculty['first_name']} {$faculty['last_name']}: faculty_id={$faculty['faculty_id']}, user_id={$faculty['user_id']}, email={$faculty['email']}\n";
    }
    $message = $debug_dropdown;
    $message_type = "success";
    // Reset the result pointer
    mysqli_data_seek($faculty_members_result, 0);
}

if ($faculty_count == 0) {
    $message = "No faculty found in department '{$teacher_department}'. Please contact administrator.";
    $message_type = "error";
}

// Get existing peer evaluations
$existing_evaluations_query = "SELECT es.*, mec.name as category_name,
                              COALESCE(f.first_name, u.first_name) as evaluatee_first_name,
                              COALESCE(f.last_name, u.last_name) as evaluatee_last_name
                              FROM evaluation_sessions es
                              JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                              LEFT JOIN users u ON es.evaluatee_id = u.id
                              LEFT JOIN faculty f ON es.evaluatee_id = f.id
                              WHERE es.evaluator_id = ? AND mec.evaluation_type = 'peer_to_peer'
                              ORDER BY es.created_at DESC";
$existing_evaluations_stmt = mysqli_prepare($conn, $existing_evaluations_query);
mysqli_stmt_bind_param($existing_evaluations_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($existing_evaluations_stmt);
$existing_evaluations_result = mysqli_stmt_get_result($existing_evaluations_stmt);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<!-- Include Peer Evaluation CSS -->
<link rel="stylesheet" href="assets/css/peer-evaluation.css">

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Peer to Peer Evaluations</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Evaluate faculty members from your department
                <span class="font-medium text-seait-orange">(<?php echo htmlspecialchars($teacher_department); ?>)</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-seait-orange text-white text-xs font-medium rounded-full">
                <i class="fas fa-users mr-1"></i>Peer Evaluation
            </span>
        </div>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="message <?php echo $message_type === 'success' ? 'message-success' : 'message-error'; ?>">
    <div class="flex items-center">
        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
</div>
<?php endif; ?>

<!-- Information Alert -->
<div class="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-6 mb-6">
    <div class="flex items-start">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-500 text-xl"></i>
        </div>
        <div class="ml-4">
            <h3 class="text-lg font-medium text-blue-900 mb-2">Peer Evaluation Guidelines</h3>
            <div class="text-blue-800 space-y-2">
                <p class="flex items-center">
                    <i class="fas fa-check-circle mr-2 text-green-500"></i>
                    You can only evaluate faculty members from your own department
                    <strong class="text-blue-900">(<?php echo htmlspecialchars($teacher_department); ?>)</strong>
                </p>
                <p class="flex items-center">
                    <i class="fas fa-star mr-2 text-yellow-500"></i>
                    Each evaluation uses a standardized 1-5 rating scale across multiple criteria
                </p>
                <p class="flex items-center">
                    <i class="fas fa-clock mr-2 text-orange-500"></i>
                    You can save your progress and continue evaluations later
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Start New Evaluation -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
        <h2 class="text-xl font-bold text-seait-dark flex items-center">
            <i class="fas fa-plus-circle mr-2 text-seait-orange"></i>
            Start New Peer Evaluation
        </h2>
    </div>

    <div class="p-6">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="start_evaluation">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="form-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-1 text-seait-orange"></i>
                        Select Faculty Member <span class="text-red-500">*</span>
                    </label>
                    <select name="evaluatee_id" required class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Choose faculty member to evaluate</option>
                        <?php while ($faculty = mysqli_fetch_assoc($faculty_members_result)): ?>
                        <option value="<?php echo $faculty['user_id']; ?>">
                            <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['position'] . ')'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tag mr-1 text-seait-orange"></i>
                        Evaluation Category <span class="text-red-500">*</span>
                    </label>
                    <select name="main_category_id" required class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Choose evaluation category</option>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="action-btn btn-primary px-8 py-3 rounded-lg transition flex items-center">
                    <i class="fas fa-play mr-2"></i>Start Evaluation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Existing Peer Evaluations -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
        <h2 class="text-lg font-medium text-seait-dark flex items-center">
            <i class="fas fa-list-check mr-2 text-seait-orange"></i>
            Your Peer Evaluations
        </h2>
        <p class="text-sm text-gray-600 mt-1">Track your evaluation progress and view completed assessments</p>
    </div>

    <div class="p-6">
        <?php if (mysqli_num_rows($existing_evaluations_result) == 0): ?>
            <div class="text-center py-12">
                <i class="fas fa-users text-gray-300 text-6xl mb-6"></i>
                <h3 class="text-xl font-medium text-gray-700 mb-2">No Evaluations Yet</h3>
                <p class="text-gray-500 mb-6">You haven't conducted any peer evaluations yet. Start by selecting a faculty member above.</p>
                <div class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-600 rounded-lg">
                    <i class="fas fa-arrow-up mr-2"></i>
                    Use the form above to begin
                </div>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php while ($evaluation = mysqli_fetch_assoc($existing_evaluations_result)): ?>
                    <div class="evaluation-info-card p-6 rounded-lg border border-gray-200 hover:shadow-lg transition-all duration-200">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-center">
                                <div class="h-12 w-12 rounded-full bg-gradient-to-r from-seait-orange to-orange-500 flex items-center justify-center mr-4 shadow-lg">
                                    <i class="fas fa-users text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        <?php echo htmlspecialchars($evaluation['evaluatee_first_name'] . ' ' . $evaluation['evaluatee_last_name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 flex items-center">
                                        <i class="fas fa-tag mr-1 text-seait-orange"></i>
                                        <?php echo htmlspecialchars($evaluation['category_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 flex items-center">
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <span class="status-badge <?php
                                    echo $evaluation['status'] === 'completed' ? 'status-completed' :
                                        ($evaluation['status'] === 'draft' ? 'status-pending' : 'status-pending');
                                ?> self-start">
                                    <i class="fas <?php echo $evaluation['status'] === 'completed' ? 'fa-check' : 'fa-clock'; ?> mr-1"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $evaluation['status'])); ?>
                                </span>

                                <div class="flex gap-2">
                                    <a href="view-peer-evaluation.php?session_id=<?php echo encrypt_id($evaluation['id']); ?>"
                                       class="action-btn btn-secondary px-3 py-2 rounded-lg transition flex items-center"
                                       title="View Evaluation">
                                        <i class="fas fa-eye mr-1"></i>
                                        <span class="hidden sm:inline">View</span>
                                    </a>
                                    <?php if ($evaluation['status'] === 'draft'): ?>
                                    <a href="edit-peer-evaluation.php?session_id=<?php echo encrypt_id($evaluation['id']); ?>"
                                       class="action-btn btn-primary px-3 py-2 rounded-lg transition flex items-center"
                                       title="Continue Evaluation">
                                        <i class="fas fa-edit mr-1"></i>
                                        <span class="hidden sm:inline">Continue</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <a href="evaluations.php" class="inline-flex items-center text-seait-orange hover:text-orange-600 font-medium transition">
                    <i class="fas fa-arrow-right mr-2"></i>
                    View all evaluations
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Department Faculty List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
        <h2 class="text-lg font-medium text-seait-dark flex items-center">
            <i class="fas fa-user-friends mr-2 text-seait-orange"></i>
            Faculty in Your Department
        </h2>
        <p class="text-sm text-gray-600 mt-1">Available faculty members for peer evaluation</p>
    </div>

    <div class="p-6">
        <?php
        // Reset faculty members result pointer
        mysqli_data_seek($faculty_members_result, 0);
        $faculty_count = mysqli_num_rows($faculty_members_result);
        ?>

        <?php if ($faculty_count == 0): ?>
            <div class="text-center py-12">
                <i class="fas fa-user-friends text-gray-300 text-6xl mb-6"></i>
                <h3 class="text-xl font-medium text-gray-700 mb-2">No Other Faculty Found</h3>
                <p class="text-gray-500">No other faculty members found in your department.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php while ($faculty = mysqli_fetch_assoc($faculty_members_result)): ?>
                    <div class="evaluation-info-card p-4 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200">
                        <div class="flex items-center">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-r from-seait-orange to-orange-500 flex items-center justify-center mr-4 shadow-lg">
                                <span class="text-white font-bold text-sm">
                                    <?php echo strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                                </h3>
                                <p class="text-xs text-gray-600 flex items-center">
                                    <i class="fas fa-briefcase mr-1 text-seait-orange"></i>
                                    <?php echo htmlspecialchars($faculty['position']); ?>
                                </p>
                                <p class="text-xs text-gray-500 flex items-center">
                                    <i class="fas fa-envelope mr-1"></i>
                                    <?php echo htmlspecialchars($faculty['email']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-medium mb-1">Evaluation Eligibility</p>
                        <p>You can evaluate any of the faculty members listed above using the peer evaluation categories. Each evaluation helps maintain high standards within your department.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>