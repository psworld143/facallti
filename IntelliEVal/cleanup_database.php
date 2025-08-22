<?php
/**
 * IntelliEVal System - Database Cleanup Script
 * This script identifies and fixes redundant data across all tables
 */

session_start();
require_once '../config/database.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$message_type = '';
$cleanup_results = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'run_cleanup') {

        // Start transaction for safety
        mysqli_begin_transaction($conn);

        try {
            $total_cleaned = 0;
            $errors = [];

            // =====================================================
            // 1. CLEANUP DUPLICATE EVALUATION CATEGORIES
            // =====================================================

            // Find and remove duplicate evaluation categories
            $duplicate_categories_query = "SELECT name, COUNT(*) as count FROM evaluation_categories WHERE status = 'active' GROUP BY name HAVING COUNT(*) > 1";
            $duplicate_categories_result = mysqli_query($conn, $duplicate_categories_query);
            $duplicate_categories_count = mysqli_num_rows($duplicate_categories_result);

            if ($duplicate_categories_count > 0) {
                // Keep the first occurrence and delete the rest
                $delete_duplicates = "DELETE ec1 FROM evaluation_categories ec1
                                    INNER JOIN evaluation_categories ec2
                                    WHERE ec1.id > ec2.id
                                    AND ec1.name = ec2.name
                                    AND ec1.status = 'active'
                                    AND ec2.status = 'active'";
                mysqli_query($conn, $delete_duplicates);
                $deleted_duplicates = mysqli_affected_rows($conn);
                $total_cleaned += $deleted_duplicates;
                $cleanup_results['duplicate_categories'] = $deleted_duplicates;
            }

            // =====================================================
            // 2. CLEANUP CANCELLED EVALUATION SESSIONS
            // =====================================================

            // Find and delete cancelled evaluation sessions and their related data
            $cancelled_sessions_query = "SELECT id FROM evaluation_sessions WHERE status = 'cancelled'";
            $cancelled_sessions_result = mysqli_query($conn, $cancelled_sessions_query);
            $cancelled_sessions_count = mysqli_num_rows($cancelled_sessions_result);

            if ($cancelled_sessions_count > 0) {
                $deleted_responses = 0;
                $deleted_sessions = 0;

                while ($session = mysqli_fetch_assoc($cancelled_sessions_result)) {
                    $session_id = $session['id'];

                    // Delete related evaluation responses first
                    $delete_responses = "DELETE FROM evaluation_responses WHERE evaluation_session_id = ?";
                    $delete_responses_stmt = mysqli_prepare($conn, $delete_responses);
                    mysqli_stmt_bind_param($delete_responses_stmt, "i", $session_id);
                    mysqli_stmt_execute($delete_responses_stmt);
                    $deleted_responses += mysqli_affected_rows($conn);

                    // Delete the cancelled session
                    $delete_session = "DELETE FROM evaluation_sessions WHERE id = ?";
                    $delete_session_stmt = mysqli_prepare($conn, $delete_session);
                    mysqli_stmt_bind_param($delete_session_stmt, "i", $session_id);
                    mysqli_stmt_execute($delete_session_stmt);

                    if (mysqli_affected_rows($conn) > 0) {
                        $deleted_sessions++;
                    }
                }

                $total_cleaned += $deleted_sessions;
                $cleanup_results['cancelled_sessions'] = [
                    'sessions_deleted' => $deleted_sessions,
                    'responses_deleted' => $deleted_responses
                ];
            }

            // =====================================================
            // 3. CLEANUP DUPLICATE QUESTIONNAIRES
            // =====================================================

            // Find and remove duplicate questionnaires
            $duplicate_questionnaires_query = "SELECT COUNT(*) as count FROM questionnaires q1 INNER JOIN questionnaires q2 WHERE q1.id != q2.id AND q1.category_id = q2.category_id AND q1.question_text = q2.question_text AND q1.question_type = q2.question_type AND q1.status = 'active' AND q2.status = 'active' AND q1.id > q2.id";
            $duplicate_questionnaires_result = mysqli_query($conn, $duplicate_questionnaires_query);
            $duplicate_questionnaires_count = mysqli_fetch_assoc($duplicate_questionnaires_result)['count'];

            if ($duplicate_questionnaires_count > 0) {
                $delete_duplicate_questionnaires = "DELETE q1 FROM questionnaires q1 INNER JOIN questionnaires q2 WHERE q1.id != q2.id AND q1.category_id = q2.category_id AND q1.question_text = q2.question_text AND q1.question_type = q2.question_type AND q1.status = 'active' AND q2.status = 'active' AND q1.id > q2.id";
                if (mysqli_query($conn, $delete_duplicate_questionnaires)) {
                    $cleanup_results['duplicate_questionnaires'] = $duplicate_questionnaires_count;
                    $total_cleaned += $duplicate_questionnaires_count;
                } else {
                    $errors[] = "Error cleaning duplicate questionnaires: " . mysqli_error($conn);
                }
            }

            // =====================================================
            // 4. CLEANUP DUPLICATE EVALUATION SESSIONS
            // =====================================================

            // Find and remove duplicate evaluation sessions
            $duplicate_sessions_query = "SELECT COUNT(*) as count FROM evaluation_sessions es1 INNER JOIN evaluation_sessions es2 WHERE es1.id != es2.id AND es1.evaluator_id = es2.evaluator_id AND es1.evaluatee_id = es2.evaluatee_id AND es1.main_category_id = es2.main_category_id AND DATE(es1.evaluation_date) = DATE(es2.evaluation_date) AND es1.status = 'draft' AND es2.status = 'draft' AND es1.id > es2.id";
            $duplicate_sessions_result = mysqli_query($conn, $duplicate_sessions_query);
            $duplicate_sessions_count = mysqli_fetch_assoc($duplicate_sessions_result)['count'];

            if ($duplicate_sessions_count > 0) {
                $delete_duplicate_sessions = "DELETE es1 FROM evaluation_sessions es1 INNER JOIN evaluation_sessions es2 WHERE es1.id != es2.id AND es1.evaluator_id = es2.evaluator_id AND es1.evaluatee_id = es2.evaluatee_id AND es1.main_category_id = es2.main_category_id AND DATE(es1.evaluation_date) = DATE(es2.evaluation_date) AND es1.status = 'draft' AND es2.status = 'draft' AND es1.id > es2.id";
                if (mysqli_query($conn, $delete_duplicate_sessions)) {
                    $cleanup_results['duplicate_sessions'] = $duplicate_sessions_count;
                    $total_cleaned += $duplicate_sessions_count;
                } else {
                    $errors[] = "Error cleaning duplicate sessions: " . mysqli_error($conn);
                }
            }

            // =====================================================
            // 5. CLEANUP ORPHANED EVALUATION RESPONSES
            // =====================================================

            // Find and remove orphaned evaluation responses
            $orphaned_responses_query = "SELECT COUNT(*) as count FROM evaluation_responses er LEFT JOIN evaluation_sessions es ON er.evaluation_session_id = es.id WHERE es.id IS NULL";
            $orphaned_responses_result = mysqli_query($conn, $orphaned_responses_query);
            $orphaned_responses_count = mysqli_fetch_assoc($orphaned_responses_result)['count'];

            if ($orphaned_responses_count > 0) {
                $delete_orphaned_responses = "DELETE er FROM evaluation_responses er LEFT JOIN evaluation_sessions es ON er.evaluation_session_id = es.id WHERE es.id IS NULL";
                if (mysqli_query($conn, $delete_orphaned_responses)) {
                    $cleanup_results['orphaned_responses'] = $orphaned_responses_count;
                    $total_cleaned += $orphaned_responses_count;
                } else {
                    $errors[] = "Error cleaning orphaned responses: " . mysqli_error($conn);
                }
            }

            // =====================================================
            // 6. CLEANUP ORPHANED QUESTIONNAIRES
            // =====================================================

            // Find and remove orphaned questionnaires
            $orphaned_questionnaires_query = "SELECT COUNT(*) as count FROM questionnaires q LEFT JOIN evaluation_categories ec ON q.category_id = ec.id WHERE ec.id IS NULL";
            $orphaned_questionnaires_result = mysqli_query($conn, $orphaned_questionnaires_query);
            $orphaned_questionnaires_count = mysqli_fetch_assoc($orphaned_questionnaires_result)['count'];

            if ($orphaned_questionnaires_count > 0) {
                $delete_orphaned_questionnaires = "DELETE q FROM questionnaires q LEFT JOIN evaluation_categories ec ON q.category_id = ec.id WHERE ec.id IS NULL";
                if (mysqli_query($conn, $delete_orphaned_questionnaires)) {
                    $cleanup_results['orphaned_questionnaires'] = $orphaned_questionnaires_count;
                    $total_cleaned += $orphaned_questionnaires_count;
                } else {
                    $errors[] = "Error cleaning orphaned questionnaires: " . mysqli_error($conn);
                }
            }

            // =====================================================
            // 7. CLEANUP DUPLICATE USERS
            // =====================================================

            // Find and remove duplicate users
            $duplicate_users_query = "SELECT COUNT(*) as count FROM users u1 INNER JOIN users u2 WHERE u1.email = u2.email AND u1.status = 'active' AND u2.status = 'active' AND u1.id > u2.id";
            $duplicate_users_result = mysqli_query($conn, $duplicate_users_query);
            $duplicate_users_count = mysqli_fetch_assoc($duplicate_users_result)['count'];

            if ($duplicate_users_count > 0) {
                $delete_duplicate_users = "DELETE u1 FROM users u1 INNER JOIN users u2 WHERE u1.email = u2.email AND u1.status = 'active' AND u2.status = 'active' AND u1.id > u2.id";
                if (mysqli_query($conn, $delete_duplicate_users)) {
                    $cleanup_results['duplicate_users'] = $duplicate_users_count;
                    $total_cleaned += $duplicate_users_count;
                } else {
                    $errors[] = "Error cleaning duplicate users: " . mysqli_error($conn);
                }
            }

            // =====================================================
            // 8. CLEANUP DUPLICATE STUDENTS
            // =====================================================

            // Find and remove duplicate students
            $duplicate_students_query = "SELECT COUNT(*) as count FROM students s1 INNER JOIN students s2 WHERE s1.student_id = s2.student_id AND s1.status = 'active' AND s2.status = 'active' AND s1.id > s2.id";
            $duplicate_students_result = mysqli_query($conn, $duplicate_students_query);
            $duplicate_students_count = mysqli_fetch_assoc($duplicate_students_result)['count'];

            if ($duplicate_students_count > 0) {
                $delete_duplicate_students = "DELETE s1 FROM students s1 INNER JOIN students s2 WHERE s1.student_id = s2.student_id AND s1.status = 'active' AND s2.status = 'active' AND s1.id > s2.id";
                if (mysqli_query($conn, $delete_duplicate_students)) {
                    $cleanup_results['duplicate_students'] = $duplicate_students_count;
                    $total_cleaned += $duplicate_students_count;
                } else {
                    $errors[] = "Error cleaning duplicate students: " . mysqli_error($conn);
                }
            }

            // =====================================================
            // 9. OPTIMIZE TABLES
            // =====================================================

            // Optimize main tables
            $tables_to_optimize = [
                'users', 'students', 'teachers', 'heads', 'semesters', 'subjects',
                'evaluation_categories', 'questionnaires', 'student_evaluations',
                'evaluation_responses', 'main_evaluation_categories',
                'evaluation_sub_categories', 'evaluation_questionnaires',
                'evaluation_sessions'
            ];

            foreach ($tables_to_optimize as $table) {
                $optimize_query = "OPTIMIZE TABLE $table";
                mysqli_query($conn, $optimize_query);
            }

            $cleanup_results['tables_optimized'] = count($tables_to_optimize);

            // Commit all changes
            mysqli_commit($conn);

            if ($total_cleaned > 0) {
                $message = "Database cleanup completed successfully! Removed $total_cleaned redundant records.";
                if (!empty($errors)) {
                    $message .= " Some issues occurred: " . implode(", ", $errors);
                }
                $message_type = "success";
            } else {
                $message = "No redundant data found. Your database is already clean!";
                $message_type = "info";
            }

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error during database cleanup: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get current database statistics
$stats_queries = [
    'users' => "SELECT COUNT(*) as count FROM users WHERE status = 'active'",
    'students' => "SELECT COUNT(*) as count FROM students WHERE status = 'active'",
    'teachers' => "SELECT COUNT(*) as count FROM teachers WHERE status = 'active'",
    'heads' => "SELECT COUNT(*) as count FROM heads WHERE status = 'active'",
    'semesters' => "SELECT COUNT(*) as count FROM semesters WHERE status = 'active'",
    'subjects' => "SELECT COUNT(*) as count FROM subjects WHERE status = 'active'",
    'main_evaluation_categories' => "SELECT COUNT(*) as count FROM main_evaluation_categories WHERE status = 'active'",
    'evaluation_sub_categories' => "SELECT COUNT(*) as count FROM evaluation_sub_categories WHERE status = 'active'",
    'evaluation_questionnaires' => "SELECT COUNT(*) as count FROM evaluation_questionnaires WHERE status = 'active'",
    'evaluation_sessions' => "SELECT COUNT(*) as count FROM evaluation_sessions",
    'evaluation_responses' => "SELECT COUNT(*) as count FROM evaluation_responses"
];

$database_stats = [];
foreach ($stats_queries as $table => $query) {
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $database_stats[$table] = $row['count'];
    } else {
        $database_stats[$table] = 'Error';
    }
}

// Set page title
$page_title = 'Database Cleanup';

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Database Cleanup</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Identify and fix redundant data across all tables
            </p>
        </div>
        <a href="evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
        </a>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : ($message_type === 'info' ? 'bg-blue-50 border border-blue-200 text-blue-800' : 'bg-red-50 border border-red-200 text-red-800'); ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Current Database Statistics -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Current Database Statistics</h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($database_stats as $table => $count): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-medium text-gray-900"><?php echo ucwords(str_replace('_', ' ', $table)); ?></h3>
                            <p class="text-sm text-gray-600">Records: <?php echo $count; ?></p>
                        </div>
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-database text-blue-600 text-sm"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Cleanup Results -->
<?php if (!empty($cleanup_results)): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Cleanup Results</h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($cleanup_results as $type => $count): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-medium text-green-900"><?php echo ucwords(str_replace('_', ' ', $type)); ?></h3>
                            <?php if (is_array($count)): ?>
                                <p class="text-sm text-green-700">Sessions: <?php echo $count['sessions_deleted']; ?></p>
                                <p class="text-sm text-green-700">Responses: <?php echo $count['responses_deleted']; ?></p>
                            <?php else: ?>
                                <p class="text-sm text-green-700">Cleaned: <?php echo $count; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-green-600 text-sm"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Database Cleanup Form -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Run Database Cleanup</h2>
    </div>
    <div class="p-6">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-yellow-900 mb-2">⚠️ Important Warning</h3>
            <p class="text-yellow-700 text-sm mb-3">
                This cleanup process will permanently remove redundant data from your database. Please ensure you have a backup before proceeding.
            </p>
            <ul class="text-yellow-700 text-sm space-y-1">
                <li>• Removes duplicate evaluation categories, questionnaires, and sessions</li>
                <li>• Cleans up orphaned records that reference non-existent data</li>
                <li>• Removes duplicate users, students, teachers, and heads</li>
                <li>• Optimizes all tables for better performance</li>
                <li>• This action cannot be undone</li>
            </ul>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-blue-900 mb-2">What Will Be Cleaned</h3>
            <ul class="text-blue-700 text-sm space-y-1">
                <li>• <strong>Duplicate Evaluation Categories:</strong> Removes categories with the same name</li>
                <li>• <strong>Duplicate Questionnaires:</strong> Removes identical questions in the same category</li>
                <li>• <strong>Duplicate Evaluation Sessions:</strong> Removes duplicate draft sessions</li>
                <li>• <strong>Orphaned Records:</strong> Removes responses without valid sessions/questionnaires</li>
                <li>• <strong>Duplicate Users:</strong> Removes users with the same email address</li>
                <li>• <strong>Duplicate Students:</strong> Removes students with the same student ID</li>
                <li>• <strong>Table Optimization:</strong> Optimizes all tables for better performance</li>
            </ul>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="action" value="run_cleanup">

            <div class="flex justify-end">
                <button type="submit"
                        class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition"
                        onclick="return confirm('⚠️ WARNING: This will permanently remove redundant data from your database. This action cannot be undone. Are you sure you want to proceed?')">
                    <i class="fas fa-broom mr-2"></i>Run Database Cleanup
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>