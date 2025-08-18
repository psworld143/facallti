<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';
$removed_categories = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_unlinked') {

    // First, get all categories that are not linked to main categories
    $unlinked_query = "SELECT ec.*,
                      (COUNT(DISTINCT q.id) + COUNT(DISTINCT eq.id)) as questionnaire_count,
                      COUNT(DISTINCT se.id) as evaluation_count
                      FROM evaluation_categories ec
                      LEFT JOIN questionnaires q ON ec.id = q.category_id AND q.status = 'active'
                      LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
                      LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id AND eq.status = 'active'
                      LEFT JOIN student_evaluations se ON ec.id = se.category_id
                      WHERE esc.id IS NULL
                      GROUP BY ec.id";

    $unlinked_result = mysqli_query($conn, $unlinked_query);
    $unlinked_categories = [];

    while ($row = mysqli_fetch_assoc($unlinked_result)) {
        $unlinked_categories[] = $row;
    }

    if (empty($unlinked_categories)) {
        $message = "No unlinked categories found to remove.";
        $message_type = "info";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            $removed_count = 0;

            foreach ($unlinked_categories as $category) {
                // Check if category has any dependent data
                $has_dependencies = false;

                // Check for questionnaires
                if ($category['questionnaire_count'] > 0) {
                    $has_dependencies = true;
                }

                // Check for evaluations
                if ($category['evaluation_count'] > 0) {
                    $has_dependencies = true;
                }

                if ($has_dependencies) {
                    // Skip categories with dependencies
                    continue;
                }

                // Remove the category
                $delete_query = "DELETE FROM evaluation_categories WHERE id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "i", $category['id']);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $removed_categories[] = $category;
                    $removed_count++;
                }
            }

            // Commit transaction
            mysqli_commit($conn);

            if ($removed_count > 0) {
                $message = "Successfully removed {$removed_count} unlinked categories.";
                $message_type = "success";
            } else {
                $message = "No categories could be removed. All unlinked categories have dependent data.";
                $message_type = "warning";
            }

        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            $message = "Error removing categories: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get current unlinked categories for display
$current_unlinked_query = "SELECT ec.*,
                          (COUNT(DISTINCT q.id) + COUNT(DISTINCT eq.id)) as questionnaire_count,
                          COUNT(DISTINCT se.id) as evaluation_count,
                          u.first_name, u.last_name
                          FROM evaluation_categories ec
                          LEFT JOIN questionnaires q ON ec.id = q.category_id AND q.status = 'active'
                          LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
                          LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id AND eq.status = 'active'
                          LEFT JOIN student_evaluations se ON ec.id = se.category_id
                          LEFT JOIN users u ON ec.created_by = u.id
                          WHERE esc.id IS NULL
                          GROUP BY ec.id
                          ORDER BY ec.created_at DESC";

$current_unlinked_result = mysqli_query($conn, $current_unlinked_query);
$current_unlinked_categories = [];

while ($row = mysqli_fetch_assoc($current_unlinked_result)) {
    $current_unlinked_categories[] = $row;
}

// Set page title
$page_title = 'Remove Unlinked Categories';

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Remove Unlinked Categories</h1>
            <p class="text-sm sm:text-base text-gray-600">Remove categories that are not linked to main categories</p>
        </div>
        <a href="categories.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Categories
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200' : ($message_type === 'info' ? 'bg-blue-100 text-blue-700 border border-blue-200' : 'bg-red-100 text-red-700 border border-red-200')); ?>">
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
            <h3 class="text-sm font-medium text-red-800">Important Warning</h3>
            <div class="mt-2 text-sm text-red-700">
                <p><strong>This action will permanently delete categories that are not linked to main categories.</strong></p>
                <p class="mt-2">Categories with existing questions or evaluations will be skipped to prevent data loss.</p>
                <p class="mt-2">This action cannot be undone. Please review the categories below before proceeding.</p>
            </div>
        </div>
    </div>
</div>

<!-- Current Unlinked Categories -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">
            Unlinked Categories (<?php echo count($current_unlinked_categories); ?>)
        </h2>
    </div>

    <div class="p-4 sm:p-6">
        <?php if (empty($current_unlinked_categories)): ?>
            <p class="text-gray-500 text-center py-8">No unlinked categories found. All categories are properly linked to main categories.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluations</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($current_unlinked_categories as $category): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($category['description']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $category['questionnaire_count'] > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo $category['questionnaire_count']; ?> questions
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $category['evaluation_count'] > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo $category['evaluation_count']; ?> evaluations
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($category['first_name'] . ' ' . $category['last_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $category['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($category['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Remove Button -->
            <div class="mt-6 flex justify-center">
                <form method="POST" onsubmit="return confirm('Are you sure you want to remove all unlinked categories? This action cannot be undone.');">
                    <input type="hidden" name="action" value="remove_unlinked">
                    <button type="submit"
                            class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition font-medium">
                        <i class="fas fa-trash mr-2"></i>Remove All Unlinked Categories
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recently Removed Categories -->
<?php if (!empty($removed_categories)): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">
            Recently Removed Categories (<?php echo count($removed_categories); ?>)
        </h2>
    </div>

    <div class="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($removed_categories as $category): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($category['description']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($category['first_name'] . ' ' . $category['last_name']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include the shared footer
include 'includes/footer.php';
?>