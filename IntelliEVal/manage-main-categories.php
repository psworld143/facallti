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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_main_category':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $evaluation_type = $_POST['evaluation_type'];

                if (empty($name)) {
                    $message = "Main category name is required!";
                    $message_type = "error";
                } else {
                    // Check if evaluation type already exists
                    $check_query = "SELECT COUNT(*) as count FROM main_evaluation_categories WHERE evaluation_type = ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "s", $evaluation_type);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $check_row = mysqli_fetch_assoc($check_result);

                    if ($check_row['count'] > 0) {
                        $message = "An evaluation type with this name already exists!";
                        $message_type = "error";
                    } else {
                        $insert_query = "INSERT INTO main_evaluation_categories (name, description, evaluation_type, created_by) VALUES (?, ?, ?, ?)";
                        $insert_stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($insert_stmt, "sssi", $name, $description, $evaluation_type, $_SESSION['user_id']);

                        if (mysqli_stmt_execute($insert_stmt)) {
                            $message = "Main category added successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error adding main category: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                }
                break;

            case 'update_main_category':
                $main_category_id = (int)$_POST['main_category_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $evaluation_type = $_POST['evaluation_type'];
                $status = $_POST['status'];

                if (empty($name)) {
                    $message = "Main category name is required!";
                    $message_type = "error";
                } else {
                    // Check if evaluation type already exists for other categories
                    $check_query = "SELECT COUNT(*) as count FROM main_evaluation_categories WHERE evaluation_type = ? AND id != ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "si", $evaluation_type, $main_category_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $check_row = mysqli_fetch_assoc($check_result);

                    if ($check_row['count'] > 0) {
                        $message = "An evaluation type with this name already exists!";
                        $message_type = "error";
                    } else {
                        $update_query = "UPDATE main_evaluation_categories SET name = ?, description = ?, evaluation_type = ?, status = ? WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "ssssi", $name, $description, $evaluation_type, $status, $main_category_id);

                        if (mysqli_stmt_execute($update_stmt)) {
                            $message = "Main category updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error updating main category: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                }
                break;

            case 'delete_main_category':
                $main_category_id = (int)$_POST['main_category_id'];

                // Check if main category has sub-categories
                $check_query = "SELECT COUNT(*) as count FROM evaluation_sub_categories WHERE main_category_id = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "i", $main_category_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_row = mysqli_fetch_assoc($check_result);

                if ($check_row['count'] > 0) {
                    $message = "Cannot delete main category. It contains sub-categories. Please delete the sub-categories first.";
                    $message_type = "error";
                } else {
                    $delete_query = "DELETE FROM main_evaluation_categories WHERE id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($delete_stmt, "i", $main_category_id);

                    if (mysqli_stmt_execute($delete_stmt)) {
                        $message = "Main category deleted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error deleting main category: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;
        }
    }
}

// Get all main evaluation categories
$main_categories_query = "SELECT mec.*,
                         COUNT(DISTINCT esc.id) as sub_category_count,
                         COUNT(DISTINCT eq.id) as questionnaire_count
                         FROM main_evaluation_categories mec
                         LEFT JOIN evaluation_sub_categories esc ON mec.id = esc.main_category_id AND esc.status = 'active'
                         LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id AND eq.status = 'active'
                         GROUP BY mec.id
                         ORDER BY mec.created_at DESC";
$main_categories_result = mysqli_query($conn, $main_categories_query);
$main_categories = [];
while ($row = mysqli_fetch_assoc($main_categories_result)) {
    $main_categories[] = $row;
}

// Set page title
$page_title = 'Manage Main Evaluation Categories';

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Manage Main Evaluation Categories</h1>
            <p class="text-sm sm:text-base text-gray-600">Add, edit, and manage main evaluation categories</p>
        </div>
        <a href="evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
        </a>
    </div>
</div>

<!-- Add New Main Category Form -->
<div class="mb-6 bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Add New Main Category</h2>
    </div>

    <div class="p-4 sm:p-6">
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_main_category">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Category Name *</label>
                    <input type="text" id="name" name="name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                           placeholder="e.g., Student to Teacher Evaluation">
                </div>

                <div>
                    <label for="evaluation_type" class="block text-sm font-medium text-gray-700 mb-1">Evaluation Type *</label>
                    <select id="evaluation_type" name="evaluation_type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Select Evaluation Type</option>
                        <option value="student_to_teacher">Student to Teacher</option>
                        <option value="peer_to_peer">Peer to Peer</option>
                        <option value="head_to_teacher">Head to Teacher</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                          placeholder="Brief description of this evaluation category"></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-plus mr-2"></i>Add Main Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Main Categories List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Main Categories (<?php echo count($main_categories); ?>)</h2>
    </div>

    <div class="p-4 sm:p-6">
        <?php if (empty($main_categories)): ?>
            <p class="text-gray-500 text-center py-8">No main categories found. Add your first main category above.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($main_categories as $category): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-seait-orange rounded-full flex items-center justify-center mr-3">
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
                                        ?> text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($category['description']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                    <?php echo $category['sub_category_count']; ?> sub-categories
                                </span>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                    <?php echo $category['questionnaire_count']; ?> questions
                                </span>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $category['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo ucfirst($category['status']); ?>
                                </span>
                            </div>
                        </div>

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
                                <?php echo ucwords(str_replace('_', ' ', $category['evaluation_type'])); ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex space-x-2">
                                <a href="sub-categories.php?main_category_id=<?php echo $category['id']; ?>"
                                   class="text-blue-600 hover:text-blue-900 text-sm">
                                    <i class="fas fa-eye mr-1"></i>View Sub-Categories
                                </a>
                                <button onclick="editMainCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)"
                                        class="text-yellow-600 hover:text-yellow-900 text-sm">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                                <?php if ($category['sub_category_count'] == 0): ?>
                                    <button onclick="deleteMainCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')"
                                            class="text-red-600 hover:text-red-900 text-sm">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                            <a href="conduct-evaluation.php?main_category_id=<?php echo $category['id']; ?>"
                               class="bg-seait-orange text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition">
                                <i class="fas fa-play mr-1"></i>Start Evaluation
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Main Category Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Edit Main Category</h3>
            </div>

            <form id="editForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update_main_category">
                <input type="hidden" id="edit_main_category_id" name="main_category_id">

                <div>
                    <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Category Name *</label>
                    <input type="text" id="edit_name" name="name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>

                <div>
                    <label for="edit_evaluation_type" class="block text-sm font-medium text-gray-700 mb-1">Evaluation Type *</label>
                    <select id="edit_evaluation_type" name="evaluation_type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="student_to_teacher">Student to Teacher</option>
                        <option value="peer_to_peer">Peer to Peer</option>
                        <option value="head_to_teacher">Head to Teacher</option>
                        <option value="teacher_to_student">Teacher to Student</option>
                        <option value="self_evaluation">Self Evaluation</option>
                        <option value="parent_to_teacher">Parent to Teacher</option>
                        <option value="administrator_to_teacher">Administrator to Teacher</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>

                <div>
                    <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="edit_description" name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"></textarea>
                </div>

                <div>
                    <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="edit_status" name="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Update Main Category
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
                <p class="text-gray-700 mb-4">Are you sure you want to delete the main category "<span id="deleteMainCategoryName"></span>"?</p>
                <p class="text-sm text-gray-500 mb-4">This action cannot be undone.</p>

                <form id="deleteForm" method="POST" class="flex justify-end space-x-3">
                    <input type="hidden" name="action" value="delete_main_category">
                    <input type="hidden" id="delete_main_category_id" name="main_category_id">

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
function editMainCategory(category) {
    document.getElementById('edit_main_category_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description;
    document.getElementById('edit_evaluation_type').value = category.evaluation_type;
    document.getElementById('edit_status').value = category.status;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function deleteMainCategory(id, name) {
    document.getElementById('delete_main_category_id').value = id;
    document.getElementById('deleteMainCategoryName').textContent = name;
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