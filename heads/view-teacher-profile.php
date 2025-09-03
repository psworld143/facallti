<?php
session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Get head information from heads table
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
if (!$head_stmt) {
    die("Error preparing head statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($head_stmt, "i", $user_id);
if (!mysqli_stmt_execute($head_stmt)) {
    die("Error executing head statement: " . mysqli_stmt_error($head_stmt));
}

$head_result = mysqli_stmt_get_result($head_stmt);
if (!$head_result) {
    die("Error getting head result: " . mysqli_stmt_error($head_stmt));
}

$head_info = mysqli_fetch_assoc($head_result);

// Check if head info exists
if (!$head_info) {
    header('Location: ../index.php?error=unauthorized');
    exit();
}

// Get faculty ID from URL and decrypt it
$encrypted_faculty_id = isset($_GET['faculty_id']) ? $_GET['faculty_id'] : '';
$faculty_id = 0;

if (!empty($encrypted_faculty_id)) {
    try {
        $faculty_id = IDEncryption::decrypt($encrypted_faculty_id);
    } catch (Exception $e) {
        // Log the error and redirect
        error_log("ID decryption failed: " . $e->getMessage());
        header('Location: teachers.php?error=invalid_faculty');
        exit();
    }
}

if (!$faculty_id) {
    header('Location: teachers.php?error=invalid_faculty');
    exit();
}

// Get faculty information
$faculty_query = "SELECT f.* FROM faculty f WHERE f.id = ? AND f.department = ?";

$faculty_stmt = mysqli_prepare($conn, $faculty_query);
if (!$faculty_stmt) {
    die("Error preparing faculty statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($faculty_stmt, "is", $faculty_id, $head_info['department']);
if (!mysqli_stmt_execute($faculty_stmt)) {
    die("Error executing faculty statement: " . mysqli_stmt_error($faculty_stmt));
}

$faculty_result = mysqli_stmt_get_result($faculty_stmt);
if (!$faculty_result) {
    die("Error getting faculty result: " . mysqli_stmt_error($faculty_stmt));
}

$faculty = mysqli_fetch_assoc($faculty_result);

// Check if faculty exists and belongs to head's department
if (!$faculty) {
    header('Location: teachers.php?error=faculty_not_found');
    exit();
}

// Get evaluation statistics
$eval_query = "SELECT 
    COUNT(*) as total_evaluations,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_evaluations,
    COUNT(CASE WHEN status = 'draft' THEN 1 END) as pending_evaluations,
    (SELECT AVG(er.rating_value) 
     FROM evaluation_responses er 
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id 
     WHERE es2.evaluatee_id = es.evaluatee_id 
     AND es2.evaluatee_type = 'teacher' 
     AND es2.evaluator_id = ? 
     AND es2.evaluator_type = 'head' 
     AND es2.status = 'completed' 
     AND er.rating_value IS NOT NULL) as average_rating
    FROM evaluation_sessions es
    WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher' AND es.evaluator_id = ? AND es.evaluator_type = 'head'";

$eval_stmt = mysqli_prepare($conn, $eval_query);
if (!$eval_stmt) {
    die("Error preparing evaluation statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($eval_stmt, "iii", $user_id, $faculty_id, $user_id);
if (!mysqli_stmt_execute($eval_stmt)) {
    die("Error executing evaluation statement: " . mysqli_stmt_error($eval_stmt));
}

$eval_result = mysqli_stmt_get_result($eval_stmt);
if (!$eval_result) {
    die("Error getting evaluation result: " . mysqli_stmt_error($eval_stmt));
}

$evaluation_stats = mysqli_fetch_assoc($eval_result);

// Get recent evaluations
$recent_eval_query = "SELECT es.*, 
    DATE_FORMAT(es.created_at, '%M %d, %Y') as formatted_date,
    (SELECT AVG(er.rating_value) 
     FROM evaluation_responses er 
     WHERE er.evaluation_session_id = es.id 
     AND er.rating_value IS NOT NULL) as overall_rating,
    CASE 
        WHEN (SELECT AVG(er.rating_value) 
              FROM evaluation_responses er 
              WHERE er.evaluation_session_id = es.id 
              AND er.rating_value IS NOT NULL) >= 4.5 THEN 'Excellent'
        WHEN (SELECT AVG(er.rating_value) 
              FROM evaluation_responses er 
              WHERE er.evaluation_session_id = es.id 
              AND er.rating_value IS NOT NULL) >= 3.5 THEN 'Very Good'
        WHEN (SELECT AVG(er.rating_value) 
              FROM evaluation_responses er 
              WHERE er.evaluation_session_id = es.id 
              AND er.rating_value IS NOT NULL) >= 2.5 THEN 'Good'
        WHEN (SELECT AVG(er.rating_value) 
              FROM evaluation_responses er 
              WHERE er.evaluation_session_id = es.id 
              AND er.rating_value IS NOT NULL) >= 1.5 THEN 'Fair'
        ELSE 'Poor'
    END as rating_label
    FROM evaluation_sessions es 
    WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher' 
    AND es.evaluator_id = ? AND es.evaluator_type = 'head'
    ORDER BY es.created_at DESC 
    LIMIT 5";

$recent_eval_stmt = mysqli_prepare($conn, $recent_eval_query);
if (!$recent_eval_stmt) {
    die("Error preparing recent evaluation statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($recent_eval_stmt, "ii", $faculty_id, $user_id);
if (!mysqli_stmt_execute($recent_eval_stmt)) {
    die("Error executing recent evaluation statement: " . mysqli_stmt_error($recent_eval_stmt));
}

$recent_eval_result = mysqli_stmt_get_result($recent_eval_stmt);
if (!$recent_eval_result) {
    die("Error getting recent evaluation result: " . mysqli_stmt_error($recent_eval_stmt));
}

$recent_evaluations = [];
while ($row = mysqli_fetch_assoc($recent_eval_result)) {
    $recent_evaluations[] = $row;
}

// Set page title
$page_title = 'Teacher Profile - ' . $faculty['first_name'] . ' ' . $faculty['last_name'];

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Teacher Profile</h1>
            <p class="text-gray-600"><?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?> - <?php echo $head_info['department']; ?> Department</p>
        </div>
        <div class="flex space-x-3">
            <a href="teachers.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
            <a href="edit-faculty.php?id=<?php echo IDEncryption::encrypt($faculty['id']); ?>" 
               class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-edit mr-2"></i>Edit Profile
            </a>
        </div>
    </div>
</div>

<!-- Teacher Profile Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column - Basic Info -->
    <div class="lg:col-span-1">
        <!-- Profile Card -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="text-center mb-6">
                <?php if (!empty($faculty['image_url']) && file_exists('../' . $faculty['image_url'])): ?>
                    <img class="h-32 w-32 rounded-full object-cover mx-auto mb-4 border-4 border-gray-200" 
                         src="../<?php echo htmlspecialchars($faculty['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>">
                <?php else: ?>
                    <div class="h-32 w-32 rounded-full bg-gray-300 flex items-center justify-center mx-auto mb-4 border-4 border-gray-200">
                        <span class="text-4xl font-bold text-gray-700">
                            <?php echo strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)); ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <h2 class="text-xl font-bold text-gray-900"><?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?></h2>
                <p class="text-gray-600"><?php echo $faculty['position']; ?></p>
                <p class="text-sm text-gray-500"><?php echo $head_info['department']; ?> Department</p>
            </div>
            
            <div class="space-y-4">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-id-card text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Faculty ID</p>
                        <p class="text-sm text-gray-600"><?php echo !empty($faculty['qrcode']) ? htmlspecialchars($faculty['qrcode']) : 'Not assigned'; ?></p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-envelope text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Email</p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($faculty['email']); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($faculty['middle_name'])): ?>
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Middle Name</p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($faculty['middle_name']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-circle text-orange-600"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Status</p>
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $faculty['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $faculty['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <a href="view-evaluation.php?faculty_id=<?php echo IDEncryption::encrypt($faculty['id']); ?>" 
                   class="w-full flex items-center justify-center px-4 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>View Evaluations
                </a>
                

            </div>
        </div>
    </div>
    
    <!-- Right Column - Statistics and Recent Evaluations -->
    <div class="lg:col-span-2">
        <!-- Evaluation Statistics -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Evaluation Statistics</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $evaluation_stats['total_evaluations']; ?></div>
                    <div class="text-sm text-blue-600">Total Evaluations</div>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600"><?php echo $evaluation_stats['completed_evaluations']; ?></div>
                    <div class="text-sm text-green-600">Completed</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600"><?php echo $evaluation_stats['pending_evaluations']; ?></div>
                    <div class="text-sm text-yellow-600">Pending</div>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">
                        <?php echo $evaluation_stats['average_rating'] ? number_format($evaluation_stats['average_rating'], 1) : 'N/A'; ?>
                    </div>
                    <div class="text-sm text-purple-600">Average Rating</div>
                </div>
            </div>
        </div>
        
        <!-- Recent Evaluations -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Recent Evaluations</h3>
                <a href="view-evaluation.php?faculty_id=<?php echo IDEncryption::encrypt($faculty['id']); ?>" 
                   class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (empty($recent_evaluations)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-chart-bar text-4xl mb-3 text-gray-300"></i>
                    <p>No evaluations found for this teacher.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recent_evaluations as $evaluation): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-chart-bar text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        Evaluation on <?php echo $evaluation['formatted_date']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Status: 
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo ucfirst($evaluation['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php if ($evaluation['status'] === 'completed' && $evaluation['overall_rating']): ?>
                                    <div class="text-lg font-bold text-gray-900"><?php echo number_format($evaluation['overall_rating'], 1); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $evaluation['rating_label']; ?></div>
                                <?php else: ?>
                                    <div class="text-sm text-gray-400">Not rated</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<script>



</script>
