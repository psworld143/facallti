<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        $deleted_count = 0;

        // 1. Delete all evaluation responses for peer evaluations
        $delete_responses_query = "DELETE er FROM evaluation_responses er
                                  INNER JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                                  INNER JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                                  WHERE mec.evaluation_type = 'peer_to_peer'";
        $delete_responses_result = mysqli_query($conn, $delete_responses_query);
        if ($delete_responses_result) {
            $deleted_responses = mysqli_affected_rows($conn);
            $deleted_count += $deleted_responses;
        }

        // 2. Delete all evaluation sessions for peer evaluations
        $delete_sessions_query = "DELETE es FROM evaluation_sessions es
                                 INNER JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                                 WHERE mec.evaluation_type = 'peer_to_peer'";
        $delete_sessions_result = mysqli_query($conn, $delete_sessions_query);
        if ($delete_sessions_result) {
            $deleted_sessions = mysqli_affected_rows($conn);
            $deleted_count += $deleted_sessions;
        }

        // 3. Delete all questionnaires for peer evaluation sub-categories
        $delete_questionnaires_query = "DELETE eq FROM evaluation_questionnaires eq
                                       INNER JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
                                       INNER JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                                       WHERE mec.evaluation_type = 'peer_to_peer'";
        $delete_questionnaires_result = mysqli_query($conn, $delete_questionnaires_query);
        if ($delete_questionnaires_result) {
            $deleted_questionnaires = mysqli_affected_rows($conn);
            $deleted_count += $deleted_questionnaires;
        }

        // 4. Delete all sub-categories for peer evaluations
        $delete_sub_categories_query = "DELETE esc FROM evaluation_sub_categories esc
                                       INNER JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                                       WHERE mec.evaluation_type = 'peer_to_peer'";
        $delete_sub_categories_result = mysqli_query($conn, $delete_sub_categories_query);
        if ($delete_sub_categories_result) {
            $deleted_sub_categories = mysqli_affected_rows($conn);
            $deleted_count += $deleted_sub_categories;
        }

        // 5. Delete the main peer evaluation category
        $delete_main_category_query = "DELETE FROM main_evaluation_categories WHERE evaluation_type = 'peer_to_peer'";
        $delete_main_category_result = mysqli_query($conn, $delete_main_category_query);
        if ($delete_main_category_result) {
            $deleted_main_categories = mysqli_affected_rows($conn);
            $deleted_count += $deleted_main_categories;
        }

        // Commit transaction
        mysqli_commit($conn);

        $message = "Successfully deleted all peer evaluation data. Total records deleted: $deleted_count";
        $message_type = "success";

    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $message = "Error deleting peer evaluation data: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get current peer evaluation data count for display
$counts = [];

// Count evaluation sessions
$sessions_count_query = "SELECT COUNT(*) as count FROM evaluation_sessions es
                        INNER JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                        WHERE mec.evaluation_type = 'peer_to_peer'";
$sessions_count_result = mysqli_query($conn, $sessions_count_query);
$counts['sessions'] = mysqli_fetch_assoc($sessions_count_result)['count'];

// Count evaluation responses
$responses_count_query = "SELECT COUNT(*) as count FROM evaluation_responses er
                         INNER JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                         INNER JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                         WHERE mec.evaluation_type = 'peer_to_peer'";
$responses_count_result = mysqli_query($conn, $responses_count_query);
$counts['responses'] = mysqli_fetch_assoc($responses_count_result)['count'];

// Count questionnaires
$questionnaires_count_query = "SELECT COUNT(*) as count FROM evaluation_questionnaires eq
                              INNER JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
                              INNER JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                              WHERE mec.evaluation_type = 'peer_to_peer'";
$questionnaires_count_result = mysqli_query($conn, $questionnaires_count_query);
$counts['questionnaires'] = mysqli_fetch_assoc($questionnaires_count_result)['count'];

// Count sub-categories
$sub_categories_count_query = "SELECT COUNT(*) as count FROM evaluation_sub_categories esc
                              INNER JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                              WHERE mec.evaluation_type = 'peer_to_peer'";
$sub_categories_count_result = mysqli_query($conn, $sub_categories_count_query);
$counts['sub_categories'] = mysqli_fetch_assoc($sub_categories_count_result)['count'];

// Count main categories
$main_categories_count_query = "SELECT COUNT(*) as count FROM main_evaluation_categories WHERE evaluation_type = 'peer_to_peer'";
$main_categories_count_result = mysqli_query($conn, $main_categories_count_query);
$counts['main_categories'] = mysqli_fetch_assoc($main_categories_count_result)['count'];

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Delete Peer Evaluation Data</h1>
            <p class="text-sm sm:text-base text-gray-600">Remove all peer evaluation data from the database</p>
        </div>
        <div class="flex space-x-2">
            <a href="peer-evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Peer Evaluations
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

<!-- Warning Alert -->
<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-exclamation-triangle text-red-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">⚠️ Warning: Irreversible Action</h3>
            <div class="mt-2 text-sm text-red-700">
                <p>This action will permanently delete ALL peer evaluation data from the database. This includes:</p>
                <ul class="list-disc list-inside mt-2 space-y-1">
                    <li>All peer evaluation sessions</li>
                    <li>All evaluation responses</li>
                    <li>All peer evaluation questionnaires</li>
                    <li>All peer evaluation sub-categories</li>
                    <li>The main peer evaluation category</li>
                </ul>
                <p class="mt-2 font-medium">This action cannot be undone!</p>
            </div>
        </div>
    </div>
</div>

<!-- Current Data Summary -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-seait-dark mb-4">Current Peer Evaluation Data</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="font-medium text-gray-900 mb-2">Main Categories</h3>
            <p class="text-2xl font-bold text-seait-orange"><?php echo $counts['main_categories']; ?></p>
        </div>

        <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="font-medium text-gray-900 mb-2">Sub-Categories</h3>
            <p class="text-2xl font-bold text-seait-orange"><?php echo $counts['sub_categories']; ?></p>
        </div>

        <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="font-medium text-gray-900 mb-2">Questionnaires</h3>
            <p class="text-2xl font-bold text-seait-orange"><?php echo $counts['questionnaires']; ?></p>
        </div>

        <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="font-medium text-gray-900 mb-2">Evaluation Sessions</h3>
            <p class="text-2xl font-bold text-seait-orange"><?php echo $counts['sessions']; ?></p>
        </div>

        <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="font-medium text-gray-900 mb-2">Evaluation Responses</h3>
            <p class="text-2xl font-bold text-seait-orange"><?php echo $counts['responses']; ?></p>
        </div>

        <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="font-medium text-gray-900 mb-2">Total Records</h3>
            <p class="text-2xl font-bold text-red-600"><?php echo array_sum($counts); ?></p>
        </div>
    </div>
</div>

<!-- Confirmation Form -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-bold text-seait-dark mb-4">Confirm Deletion</h2>

    <?php if (array_sum($counts) > 0): ?>
    <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete ALL peer evaluation data? This action cannot be undone!');">
        <input type="hidden" name="action" value="delete_all">

        <div class="mb-6">
            <p class="text-gray-700 mb-4">To confirm deletion, please check the box below:</p>
            <label class="flex items-center">
                <input type="checkbox" required class="mr-3 text-red-600 focus:ring-red-500">
                <span class="text-sm text-gray-700">I understand that this will permanently delete all peer evaluation data and this action cannot be undone.</span>
            </label>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="peer-evaluations.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition">
                Cancel
            </a>
            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition">
                <i class="fas fa-trash mr-2"></i>Delete All Peer Evaluation Data
            </button>
        </div>
    </form>
    <?php else: ?>
    <div class="text-center py-8">
        <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
        <p class="text-gray-500">No peer evaluation data found in the database.</p>
        <div class="mt-4">
            <a href="peer-evaluations.php" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Peer Evaluations
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>