<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Evaluation Categories';

$message = '';
$message_type = '';

// Get main evaluation categories for the dropdown
$main_categories_query = "SELECT id, name, evaluation_type FROM main_evaluation_categories WHERE status = 'active' ORDER BY name ASC";
$main_categories_result = mysqli_query($conn, $main_categories_query);
$main_categories = [];
while ($row = mysqli_fetch_assoc($main_categories_result)) {
    $main_categories[] = $row;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                $name = sanitize_input($_POST['name']);
                $description = sanitize_input($_POST['description']);
                $main_category_id = (int)$_POST['main_category_id'];

                if (empty($name)) {
                    $message = "Category name is required!";
                    $message_type = "error";
                } elseif ($main_category_id <= 0) {
                    $message = "Please select a main category!";
                    $message_type = "error";
                } else {
                    // First, create the category in the old table
                    $insert_query = "INSERT INTO evaluation_categories (name, description, created_by) VALUES (?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "ssi", $name, $description, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $category_id = mysqli_insert_id($conn);

                        // Also create it as a sub-category in the new hierarchical structure
                        $sub_category_query = "INSERT INTO evaluation_sub_categories (main_category_id, name, description, created_by) VALUES (?, ?, ?, ?)";
                        $sub_category_stmt = mysqli_prepare($conn, $sub_category_query);
                        mysqli_stmt_bind_param($sub_category_stmt, "issi", $main_category_id, $name, $description, $_SESSION['user_id']);

                        if (mysqli_stmt_execute($sub_category_stmt)) {
                            $message = "Evaluation category added successfully! It has been created in both the old and new hierarchical structure.";
                            $message_type = "success";
                        } else {
                            $message = "Category added to old structure but failed to add to new structure: " . mysqli_error($conn);
                            $message_type = "warning";
                        }
                    } else {
                        $message = "Error adding category: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;
        }
    }
}

// Get evaluation categories with main category information
$categories_query = "SELECT ec.*,
                    (COUNT(DISTINCT q.id) + COUNT(DISTINCT eq.id)) as questionnaire_count,
                    u.first_name, u.last_name,
                    esc.main_category_id, esc.id as sub_category_id, mec.name as main_category_name, mec.evaluation_type
                    FROM evaluation_categories ec
                    LEFT JOIN questionnaires q ON ec.id = q.category_id AND q.status = 'active'
                    LEFT JOIN users u ON ec.created_by = u.id
                    LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
                    LEFT JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                    LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id AND eq.status = 'active'
                    GROUP BY ec.id
                    ORDER BY ec.created_at DESC";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Evaluation Categories</h1>
            <p class="text-sm sm:text-base text-gray-600">Manage evaluation categories and link them to main categories</p>
        </div>
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
            <h3 class="text-sm font-medium text-blue-800">Category Management</h3>
            <div class="mt-2 text-sm text-blue-700">
                <p>Manage evaluation categories and link them to main categories in the hierarchical structure. <strong>New categories will be automatically added to the hierarchical structure.</strong></p>
                <p class="mt-2"><strong>Note:</strong> Categories that show "Link to Main Category First" need to be linked to a main category before you can manage their questions. Use the "Link Categories" button to connect them to the hierarchical structure.</p>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="mb-6 flex flex-col sm:flex-row gap-3">
    <a href="manage-main-categories.php" class="w-full sm:w-auto bg-blue-600 text-white px-4 sm:px-6 py-2 sm:py-2 rounded-lg hover:bg-blue-700 transition text-sm sm:text-base text-center">
        <i class="fas fa-cog mr-2"></i>Manage Main Categories
    </a>
    <button onclick="openAddCategoryModal()" class="w-full sm:w-auto bg-seait-orange text-white px-4 sm:px-6 py-2 sm:py-2 rounded-lg hover:bg-orange-600 transition text-sm sm:text-base">
        <i class="fas fa-plus mr-2"></i>Add New Category
    </button>
    <a href="link-existing-categories.php" class="w-full sm:w-auto bg-blue-600 text-white px-4 sm:px-6 py-2 sm:py-2 rounded-lg hover:bg-blue-700 transition text-sm sm:text-base text-center">
        <i class="fas fa-link mr-2"></i>Link Existing Categories
    </a>
    <a href="remove-unlinked-categories.php" class="w-full sm:w-auto bg-red-600 text-white px-4 sm:px-6 py-2 sm:py-2 rounded-lg hover:bg-red-700 transition text-sm sm:text-base text-center">
        <i class="fas fa-trash mr-2"></i>Remove Unlinked Categories
    </a>
</div>

<!-- Categories List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Categories (<?php echo count($categories); ?>)</h2>
    </div>

    <div class="p-4 sm:p-6">
        <?php if (empty($categories)): ?>
            <p class="text-gray-500 text-center py-8">No categories found. Add your first category above.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                <?php foreach ($categories as $category): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h3>
                        <div class="flex flex-col items-end space-y-1">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $category['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo ucfirst($category['status']); ?>
                            </span>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($category['description']); ?></p>

                    <?php if ($category['main_category_name']): ?>
                    <div class="mb-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?php
                            switch($category['evaluation_type']) {
                                case 'student_to_teacher':
                                    echo 'bg-orange-100 text-orange-800';
                                    break;
                                case 'peer_to_peer':
                                    echo 'bg-purple-100 text-purple-800';
                                    break;
                                case 'head_to_teacher':
                                    echo 'bg-indigo-100 text-indigo-800';
                                    break;
                                case 'teacher_to_student':
                                    echo 'bg-blue-100 text-blue-800';
                                    break;
                                case 'self_evaluation':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'parent_to_teacher':
                                    echo 'bg-pink-100 text-pink-800';
                                    break;
                                case 'administrator_to_teacher':
                                    echo 'bg-red-100 text-red-800';
                                    break;
                                default:
                                    echo 'bg-gray-100 text-gray-800';
                                    break;
                            }
                            ?>">
                            <i class="fas
                                <?php
                                switch($category['evaluation_type']) {
                                    case 'student_to_teacher':
                                        echo 'fa-user-graduate';
                                        break;
                                    case 'peer_to_peer':
                                        echo 'fa-users';
                                        break;
                                    case 'head_to_teacher':
                                        echo 'fa-user-tie';
                                        break;
                                    case 'teacher_to_student':
                                        echo 'fa-chalkboard-teacher';
                                        break;
                                    case 'self_evaluation':
                                        echo 'fa-user-check';
                                        break;
                                    case 'parent_to_teacher':
                                        echo 'fa-user-friends';
                                        break;
                                    case 'administrator_to_teacher':
                                        echo 'fa-user-shield';
                                        break;
                                    default:
                                        echo 'fa-list';
                                        break;
                                }
                                ?> mr-1"></i>
                            <?php echo htmlspecialchars($category['main_category_name']); ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                        <span><i class="fas fa-question-circle mr-1"></i><?php echo $category['questionnaire_count']; ?> questions</span>
                        <span class="hidden sm:inline"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($category['first_name'] . ' ' . $category['last_name']); ?></span>
                    </div>

                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                        <?php if ($category['sub_category_id']): ?>
                            <a href="questionnaires.php?sub_category_id=<?php echo $category['sub_category_id']; ?>"
                               class="text-center text-blue-600 hover:text-blue-900 text-sm bg-blue-50 hover:bg-blue-100 px-3 py-2 rounded-lg transition">
                                <i class="fas fa-list mr-1"></i>Manage Questions
                            </a>
                        <?php else: ?>
                            <div class="flex flex-col space-y-1">
                                <span class="text-center text-gray-400 text-xs bg-gray-50 px-3 py-2 rounded-lg cursor-not-allowed">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Link to Main Category First
                                </span>
                                <a href="link-existing-categories.php"
                                   class="text-center text-blue-600 hover:text-blue-900 text-xs bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded transition">
                                    <i class="fas fa-link mr-1"></i>Link Categories
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Add New Category</h3>
            </div>

            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_category">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category Name *</label>
                    <input type="text" name="name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                           placeholder="e.g., Classroom Management">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Main Category *</label>
                    <select name="main_category_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Select Main Category</option>
                        <?php foreach ($main_categories as $main_category): ?>
                            <option value="<?php echo $main_category['id']; ?>">
                                <?php echo htmlspecialchars($main_category['name']); ?>
                                (<?php echo ucwords(str_replace('_', ' ', $main_category['evaluation_type'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                              placeholder="Brief description of this category"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAddCategoryModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.remove('hidden');
}

function closeAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('addCategoryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddCategoryModal();
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>