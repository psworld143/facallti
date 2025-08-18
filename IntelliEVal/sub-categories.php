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

// Get main category ID from URL
$main_category_id = isset($_GET['main_category_id']) ? (int)$_GET['main_category_id'] : 0;

if (!$main_category_id) {
    header('Location: evaluations.php');
    exit();
}

// Get main category information
$main_category_query = "SELECT * FROM main_evaluation_categories WHERE id = ?";
$main_category_stmt = mysqli_prepare($conn, $main_category_query);
mysqli_stmt_bind_param($main_category_stmt, "i", $main_category_id);
mysqli_stmt_execute($main_category_stmt);
$main_category_result = mysqli_stmt_get_result($main_category_stmt);
$main_category = mysqli_fetch_assoc($main_category_result);

if (!$main_category) {
    header('Location: evaluations.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_sub_category':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $order_number = (int)$_POST['order_number'];

                if (empty($name)) {
                    $message = "Sub-category name is required!";
                    $message_type = "error";
                } else {
                    $insert_query = "INSERT INTO evaluation_sub_categories (main_category_id, name, description, order_number, created_by) VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "issii", $main_category_id, $name, $description, $order_number, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = "Sub-category added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding sub-category: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'update_sub_category':
                $sub_category_id = (int)$_POST['sub_category_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $order_number = (int)$_POST['order_number'];
                $status = $_POST['status'];

                if (empty($name)) {
                    $message = "Sub-category name is required!";
                    $message_type = "error";
                } else {
                    $update_query = "UPDATE evaluation_sub_categories SET name = ?, description = ?, order_number = ?, status = ? WHERE id = ? AND main_category_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "ssissi", $name, $description, $order_number, $status, $sub_category_id, $main_category_id);

                    if (mysqli_stmt_execute($update_stmt)) {
                        $message = "Sub-category updated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error updating sub-category: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'delete_sub_category':
                $sub_category_id = (int)$_POST['sub_category_id'];

                // Check if sub-category has questionnaires
                $check_query = "SELECT COUNT(*) as count FROM evaluation_questionnaires WHERE sub_category_id = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "i", $sub_category_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_row = mysqli_fetch_assoc($check_result);

                if ($check_row['count'] > 0) {
                    $message = "Cannot delete sub-category. It contains questionnaires. Please delete the questionnaires first.";
                    $message_type = "error";
                } else {
                    $delete_query = "DELETE FROM evaluation_sub_categories WHERE id = ? AND main_category_id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($delete_stmt, "ii", $sub_category_id, $main_category_id);

                    if (mysqli_stmt_execute($delete_stmt)) {
                        $message = "Sub-category deleted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error deleting sub-category: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;
        }
    }
}

// Get sub-categories for this main category
$sub_categories_query = "SELECT esc.*, COUNT(eq.id) as questionnaire_count
                        FROM evaluation_sub_categories esc
                        LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id AND eq.status = 'active'
                        WHERE esc.main_category_id = ?
                        GROUP BY esc.id
                        ORDER BY esc.order_number ASC, esc.name ASC";
$sub_categories_stmt = mysqli_prepare($conn, $sub_categories_query);
mysqli_stmt_bind_param($sub_categories_stmt, "i", $main_category_id);
mysqli_stmt_execute($sub_categories_stmt);
$sub_categories_result = mysqli_stmt_get_result($sub_categories_stmt);
$sub_categories = [];
while ($row = mysqli_fetch_assoc($sub_categories_result)) {
    $sub_categories[] = $row;
}

// Set page title
$page_title = 'Sub-Categories - ' . $main_category['name'];

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Sub-Categories</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Manage sub-categories for: <strong><?php echo htmlspecialchars($main_category['name']); ?></strong>
            </p>
        </div>
        <a href="evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
        </a>
    </div>
</div>

<!-- Main Category Info -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800"><?php echo htmlspecialchars($main_category['name']); ?></h3>
            <div class="mt-1 text-sm text-blue-700">
                <p><?php echo htmlspecialchars($main_category['description']); ?></p>
                <p class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        <?php
                        switch($main_category['evaluation_type']) {
                            case 'student_to_teacher':
                                echo 'bg-orange-100 text-orange-800';
                                break;
                            case 'peer_to_peer':
                                echo 'bg-purple-100 text-purple-800';
                                break;
                            case 'head_to_teacher':
                                echo 'bg-indigo-100 text-indigo-800';
                                break;
                        }
                        ?>">
                        <i class="fas
                            <?php
                            switch($main_category['evaluation_type']) {
                                case 'student_to_teacher':
                                    echo 'fa-user-graduate';
                                    break;
                                case 'peer_to_peer':
                                    echo 'fa-users';
                                    break;
                                case 'head_to_teacher':
                                    echo 'fa-user-tie';
                                    break;
                            }
                            ?> mr-1"></i>
                        <?php echo ucwords(str_replace('_', ' ', $main_category['evaluation_type'])); ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Add New Sub-Category Form -->
<div class="mb-6 bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Add New Sub-Category</h2>
    </div>

    <div class="p-4 sm:p-6">
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_sub_category">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Sub-Category Name *</label>
                    <input type="text" id="name" name="name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                           placeholder="e.g., Classroom Management">
                </div>

                <div>
                    <label for="order_number" class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                    <input type="number" id="order_number" name="order_number" value="1" min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                          placeholder="Brief description of this sub-category"></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-plus mr-2"></i>Add Sub-Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Sub-Categories List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Sub-Categories (<?php echo count($sub_categories); ?>)</h2>
    </div>

    <div class="p-4 sm:p-6">
        <?php if (empty($sub_categories)): ?>
            <p class="text-gray-500 text-center py-8">No sub-categories found. Add your first sub-category above.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($sub_categories as $sub_category): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center mr-3">
                                    <span class="text-white text-sm font-medium"><?php echo $sub_category['order_number']; ?></span>
                                </div>
                                <div>
                                    <h3 class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($sub_category['name']); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($sub_category['description']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                    <?php echo $sub_category['questionnaire_count']; ?> questions
                                </span>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $sub_category['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo ucfirst($sub_category['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex space-x-2">
                                <a href="questionnaires.php?sub_category_id=<?php echo $sub_category['id']; ?>"
                                   class="text-blue-600 hover:text-blue-900 text-sm">
                                    <i class="fas fa-eye mr-1"></i>View Questionnaires
                                </a>
                                <button onclick="editSubCategory(<?php echo htmlspecialchars(json_encode($sub_category)); ?>)"
                                        class="text-yellow-600 hover:text-yellow-900 text-sm">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                                <?php if ($sub_category['questionnaire_count'] == 0): ?>
                                    <button onclick="deleteSubCategory(<?php echo $sub_category['id']; ?>, '<?php echo htmlspecialchars($sub_category['name']); ?>')"
                                            class="text-red-600 hover:text-red-900 text-sm">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Sub-Category Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Edit Sub-Category</h3>
            </div>

            <form id="editForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update_sub_category">
                <input type="hidden" id="edit_sub_category_id" name="sub_category_id">

                <div>
                    <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Sub-Category Name *</label>
                    <input type="text" id="edit_name" name="name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>

                <div>
                    <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="edit_description" name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit_order_number" class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                        <input type="number" id="edit_order_number" name="order_number" min="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    </div>

                    <div>
                        <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="edit_status" name="status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Update Sub-Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Confirm Delete</h3>
            </div>

            <div class="p-6">
                <p class="text-gray-700 mb-4">Are you sure you want to delete the sub-category "<span id="deleteSubCategoryName"></span>"?</p>
                <p class="text-sm text-gray-500 mb-4">This action cannot be undone.</p>

                <form id="deleteForm" method="POST" class="flex justify-end space-x-3">
                    <input type="hidden" name="action" value="delete_sub_category">
                    <input type="hidden" id="delete_sub_category_id" name="sub_category_id">

                    <button type="button" onclick="closeDeleteModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editSubCategory(subCategory) {
    document.getElementById('edit_sub_category_id').value = subCategory.id;
    document.getElementById('edit_name').value = subCategory.name;
    document.getElementById('edit_description').value = subCategory.description;
    document.getElementById('edit_order_number').value = subCategory.order_number;
    document.getElementById('edit_status').value = subCategory.status;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function deleteSubCategory(id, name) {
    document.getElementById('delete_sub_category_id').value = id;
    document.getElementById('deleteSubCategoryName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modals when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>