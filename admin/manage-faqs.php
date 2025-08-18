<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_faq':
                $question = trim($_POST['question']);
                $answer = trim($_POST['answer']);
                $keywords = trim($_POST['keywords']);
                $category = $_POST['category'];
                $sort_order = (int)$_POST['sort_order'];

                if (!empty($question) && !empty($answer)) {
                    $query = "INSERT INTO faqs (question, answer, keywords, category, sort_order) VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ssssi", $question, $answer, $keywords, $category, $sort_order);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = display_message('FAQ added successfully!', 'success');
                    } else {
                        $message = display_message('Error adding FAQ. Please try again.', 'error');
                    }
                } else {
                    $message = display_message('Question and answer are required.', 'error');
                }
                break;

            case 'update_faq':
                $id = (int)$_POST['id'];
                $question = trim($_POST['question']);
                $answer = trim($_POST['answer']);
                $keywords = trim($_POST['keywords']);
                $category = $_POST['category'];
                $sort_order = (int)$_POST['sort_order'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if (!empty($question) && !empty($answer)) {
                    $query = "UPDATE faqs SET question = ?, answer = ?, keywords = ?, category = ?, sort_order = ?, is_active = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ssssiii", $question, $answer, $keywords, $category, $sort_order, $is_active, $id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = display_message('FAQ updated successfully!', 'success');
                    } else {
                        $message = display_message('Error updating FAQ. Please try again.', 'error');
                    }
                } else {
                    $message = display_message('Question and answer are required.', 'error');
                }
                break;

            case 'delete_faq':
                $id = (int)$_POST['id'];
                $query = "DELETE FROM faqs WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = display_message('FAQ deleted successfully!', 'success');
                } else {
                    $message = display_message('Error deleting FAQ. Please try again.', 'error');
                }
                break;

            case 'toggle_status':
                $id = (int)$_POST['id'];
                $query = "UPDATE faqs SET is_active = NOT is_active WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = display_message('FAQ status updated successfully!', 'success');
                } else {
                    $message = display_message('Error updating FAQ status. Please try again.', 'error');
                }
                break;
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if ($category_filter !== '') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
    $param_types .= "i";
}

if ($search_query) {
    $where_conditions[] = "(question LIKE ? OR answer LIKE ? OR keywords LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

$where_clause = implode(" AND ", $where_conditions);

// Fetch FAQs with filters
$query = "SELECT * FROM faqs WHERE $where_clause ORDER BY sort_order ASC, created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$faqs_result = mysqli_stmt_get_result($stmt);

// Get categories for filter
$categories_query = "SELECT DISTINCT category FROM faqs ORDER BY category";
$categories_result = mysqli_query($conn, $categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage FAQs - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @keyframes bounce-in {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-bounce-in {
            animation: bounce-in 0.6s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/admin-header.php'; ?>

    <div class="flex pt-16">
        <?php include 'includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-64 p-8 overflow-y-auto h-screen">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-seait-dark">Manage FAQs</h1>
                <button onclick="openAddModal()" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-plus mr-2"></i>Add New FAQ
                </button>
            </div>

            <?php echo $message; ?>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <form method="GET" class="flex flex-wrap gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">All Categories</option>
                            <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo htmlspecialchars($category['category']); ?>" <?php echo $category_filter === $category['category'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($category['category'])); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                               placeholder="Search questions, answers, or keywords..."
                               class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- FAQs List -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Answer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keywords</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($faq = mysqli_fetch_assoc($faqs_result)): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs"><?php echo htmlspecialchars($faq['question']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-600 max-w-xs"><?php echo htmlspecialchars(substr($faq['answer'], 0, 100)); ?>...</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                        <?php echo ucfirst(htmlspecialchars($faq['category'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-500 max-w-xs"><?php echo htmlspecialchars($faq['keywords']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $faq['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $faq['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo $faq['sort_order']; ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium">
                                    <div class="flex items-center space-x-3">
                                        <button onclick="editFAQ(<?php echo htmlspecialchars(json_encode($faq)); ?>)" class="text-seait-orange hover:text-orange-600" title="Edit FAQ">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $faq['id']; ?>">
                                            <button type="submit" class="text-blue-600 hover:text-blue-800" title="<?php echo $faq['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $faq['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                            </button>
                                        </form>
                                        <button onclick="deleteFAQ(<?php echo $faq['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete FAQ">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add FAQ Modal -->
    <div id="add-faq-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-seait-dark">Add New FAQ</h3>
                        <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="add_faq">

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Question *</label>
                                <textarea name="question" rows="3" required
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                          placeholder="Enter the question..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Answer *</label>
                                <textarea name="answer" rows="5" required
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                          placeholder="Enter the answer..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Keywords</label>
                                <input type="text" name="keywords"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                       placeholder="Enter keywords separated by commas...">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                    <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                        <option value="general">General</option>
                                        <option value="admission">Admission</option>
                                        <option value="academics">Academics</option>
                                        <option value="contact">Contact</option>
                                        <option value="location">Location</option>
                                        <option value="fees">Fees</option>
                                        <option value="schedule">Schedule</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                                    <input type="number" name="sort_order" value="0" min="0"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" onclick="closeAddModal()"
                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                                Add FAQ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit FAQ Modal -->
    <div id="edit-faq-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-seait-dark">Edit FAQ</h3>
                        <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_faq">
                        <input type="hidden" name="id" id="edit-faq-id">

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Question *</label>
                                <textarea name="question" id="edit-faq-question" rows="3" required
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                          placeholder="Enter the question..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Answer *</label>
                                <textarea name="answer" id="edit-faq-answer" rows="5" required
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                          placeholder="Enter the answer..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Keywords</label>
                                <input type="text" name="keywords" id="edit-faq-keywords"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                       placeholder="Enter keywords separated by commas...">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                    <select name="category" id="edit-faq-category" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                        <option value="general">General</option>
                                        <option value="admission">Admission</option>
                                        <option value="academics">Academics</option>
                                        <option value="contact">Contact</option>
                                        <option value="location">Location</option>
                                        <option value="fees">Fees</option>
                                        <option value="schedule">Schedule</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                                    <input type="number" name="sort_order" id="edit-faq-sort-order" min="0"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>

                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" id="edit-faq-is-active" class="mr-2">
                                    <span class="text-sm font-medium text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" onclick="closeEditModal()"
                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                                Update FAQ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete FAQ Confirmation Modal -->
    <div id="deleteFAQModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete FAQ</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this FAQ? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    FAQ will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible to visitors
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_faq">
                        <input type="hidden" name="id" id="deleteFAQId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteFAQModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('add-faq-modal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('add-faq-modal').classList.add('hidden');
        }

        function editFAQ(faq) {
            document.getElementById('edit-faq-id').value = faq.id;
            document.getElementById('edit-faq-question').value = faq.question;
            document.getElementById('edit-faq-answer').value = faq.answer;
            document.getElementById('edit-faq-keywords').value = faq.keywords;
            document.getElementById('edit-faq-category').value = faq.category;
            document.getElementById('edit-faq-sort-order').value = faq.sort_order;
            document.getElementById('edit-faq-is-active').checked = faq.is_active == 1;

            document.getElementById('edit-faq-modal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('edit-faq-modal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.getElementById('add-faq-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        document.getElementById('edit-faq-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        function deleteFAQ(id) {
            document.getElementById('deleteFAQId').value = id;
            document.getElementById('deleteFAQModal').classList.remove('hidden');
        }

        function closeDeleteFAQModal() {
            document.getElementById('deleteFAQModal').classList.add('hidden');
        }

        // Close delete FAQ modal when clicking outside
        const deleteFAQModal = document.getElementById('deleteFAQModal');
        if (deleteFAQModal) {
            deleteFAQModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteFAQModal();
                }
            });
        }
    </script>
</body>
</html>