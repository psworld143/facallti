<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'link_categories':
                $links = $_POST['links'];
                $success_count = 0;
                $error_count = 0;

                foreach ($links as $category_id => $main_category_id) {
                    if ($main_category_id > 0) {
                        // Get category details
                        $category_query = "SELECT name, description FROM evaluation_categories WHERE id = ?";
                        $category_stmt = mysqli_prepare($conn, $category_query);
                        mysqli_stmt_bind_param($category_stmt, "i", $category_id);
                        mysqli_stmt_execute($category_stmt);
                        $category_result = mysqli_stmt_get_result($category_stmt);
                        $category = mysqli_fetch_assoc($category_result);

                        if ($category) {
                            // Check if sub-category already exists
                            $check_query = "SELECT COUNT(*) as count FROM evaluation_sub_categories WHERE name = ? AND main_category_id = ?";
                            $check_stmt = mysqli_prepare($conn, $check_query);
                            mysqli_stmt_bind_param($check_stmt, "si", $category['name'], $main_category_id);
                            mysqli_stmt_execute($check_stmt);
                            $check_result = mysqli_stmt_get_result($check_stmt);
                            $check_row = mysqli_fetch_assoc($check_result);

                            if ($check_row['count'] == 0) {
                                // Create sub-category
                                $insert_query = "INSERT INTO evaluation_sub_categories (main_category_id, name, description, created_by) VALUES (?, ?, ?, ?)";
                                $insert_stmt = mysqli_prepare($conn, $insert_query);
                                mysqli_stmt_bind_param($insert_stmt, "issi", $main_category_id, $category['name'], $category['description'], $_SESSION['user_id']);

                                if (mysqli_stmt_execute($insert_stmt)) {
                                    $success_count++;
                                } else {
                                    $error_count++;
                                }
                            } else {
                                $success_count++; // Already exists
                            }
                        } else {
                            $error_count++;
                        }
                    }
                }

                if ($success_count > 0) {
                    $message = "Successfully linked {$success_count} categories to main categories.";
                    if ($error_count > 0) {
                        $message .= " {$error_count} categories failed to link.";
                    }
                    $message_type = $error_count > 0 ? "warning" : "success";
                } else {
                    $message = "No categories were linked. Please check your selections.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get main evaluation categories
$main_categories_query = "SELECT id, name, evaluation_type FROM main_evaluation_categories WHERE status = 'active' ORDER BY name ASC";
$main_categories_result = mysqli_query($conn, $main_categories_query);
$main_categories = [];
while ($row = mysqli_fetch_assoc($main_categories_result)) {
    $main_categories[] = $row;
}

// Get unlinked categories
$unlinked_categories_query = "SELECT ec.*, COUNT(q.id) as questionnaire_count
                             FROM evaluation_categories ec
                             LEFT JOIN questionnaires q ON ec.id = q.category_id AND q.status = 'active'
                             LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
                             WHERE esc.id IS NULL
                             GROUP BY ec.id
                             ORDER BY ec.name ASC";
$unlinked_categories_result = mysqli_query($conn, $unlinked_categories_query);
$unlinked_categories = [];
while ($row = mysqli_fetch_assoc($unlinked_categories_result)) {
    $unlinked_categories[] = $row;
}

// Set page title
$page_title = 'Link Existing Categories';

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Link Existing Categories</h1>
            <p class="text-sm sm:text-base text-gray-600">Link existing evaluation categories to main categories in the hierarchical structure</p>
        </div>
        <a href="categories.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Categories
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200' : 'bg-red-100 text-red-700 border border-red-200'); ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Information Alert -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800">Link Categories</h3>
            <div class="mt-2 text-sm text-blue-700">
                <p>Select which main category each existing evaluation category should belong to. This will integrate the old flat structure with the new hierarchical system. Categories that are already linked will not appear in this list.</p>
            </div>
        </div>
    </div>
</div>

<?php if (empty($unlinked_categories)): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6 text-center">
            <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">All Categories Linked!</h3>
            <p class="text-gray-600">All existing evaluation categories have been successfully linked to main categories in the hierarchical structure.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Link Categories Form -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
            <h2 class="text-base sm:text-lg font-medium text-gray-900">Link Categories (<?php echo count($unlinked_categories); ?>)</h2>
        </div>

        <form method="POST" class="p-4 sm:p-6">
            <input type="hidden" name="action" value="link_categories">

            <div class="space-y-4">
                <?php foreach ($unlinked_categories as $category): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($category['description']); ?></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <i class="fas fa-question-circle mr-1"></i><?php echo $category['questionnaire_count']; ?> questions
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <select name="links[<?php echo $category['id']; ?>]"
                                        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                                    <option value="0">Select Main Category</option>
                                    <?php foreach ($main_categories as $main_category): ?>
                                        <option value="<?php echo $main_category['id']; ?>">
                                            <?php echo htmlspecialchars($main_category['name']); ?>
                                            (<?php echo ucwords(str_replace('_', ' ', $main_category['evaluation_type'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-link mr-2"></i>Link Selected Categories
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php
// Include the shared footer
include 'includes/footer.php';
?>