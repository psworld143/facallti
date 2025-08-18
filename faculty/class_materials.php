<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$class_id) {
    header('Location: class-management.php');
    exit();
}

// Verify the class belongs to the logged-in teacher
$class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description
                FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                WHERE tc.id = ? AND tc.teacher_id = ?";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "ii", $class_id, $_SESSION['user_id']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Set page title
$page_title = 'Class Materials';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload_material':
                $title = sanitize_input($_POST['title']);
                $description = sanitize_input($_POST['description']);
                $category_id = (int)$_POST['category_id'];
                $is_public = isset($_POST['is_public']) ? 1 : 0;

                if (empty($title)) {
                    $message = "Please provide a title for the material.";
                    $message_type = "error";
                } elseif ($category_id <= 0) {
                    $message = "Please select a category for the material.";
                    $message_type = "error";
                } else {
                    // Handle file upload
                    $file_path = '';
                    $file_name = '';
                    $mime_type = '';
                    $file_size = 0;
                    $type = 'file';

                    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/materials/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        $file_info = pathinfo($_FILES['file']['name']);
                        $file_extension = strtolower($file_info['extension']);
                        $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'zip', 'rar'];

                        if (in_array($file_extension, $allowed_extensions)) {
                            $file_name = time() . '_' . sanitize_input($_FILES['file']['name']);
                            $file_path = $file_name; // Store relative path
                            $full_path = $upload_dir . $file_name;

                            if (move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
                                $mime_type = $_FILES['file']['type'];
                                // Truncate mime_type if it's too long (safety measure)
                                if (strlen($mime_type) > 100) {
                                    $mime_type = substr($mime_type, 0, 100);
                                }
                                $file_size = $_FILES['file']['size'];

                                // Determine type based on file extension
                                if (in_array($file_extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp', 'm4v'])) {
                                    $type = 'video';
                                } elseif (in_array($file_extension, ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a', 'wma'])) {
                                    $type = 'audio';
                                } else {
                                    $type = 'file';
                                }
                            } else {
                                $message = "Error uploading file. Please try again.";
                                $message_type = "error";
                            }
                        } else {
                            $message = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
                            $message_type = "error";
                        }
                    } else {
                        $message = "Please select a file to upload.";
                        $message_type = "error";
                    }

                    // If file upload was successful, insert into database
                    if (empty($message)) {
                        $insert_query = "INSERT INTO lms_materials (class_id, category_id, title, description, file_path, file_name, file_size, mime_type, type, is_public, status, created_by, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())";
                        $insert_stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($insert_stmt, "iisssssssii", $class_id, $category_id, $title, $description, $file_path, $file_name, $file_size, $mime_type, $type, $is_public, $_SESSION['user_id']);

                        if (mysqli_stmt_execute($insert_stmt)) {
                            $message = "Material uploaded successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error uploading material: " . mysqli_error($conn);
                            $message_type = "error";
                            // Delete uploaded file if database insert fails
                            if (file_exists($full_path)) {
                                unlink($full_path);
                            }
                        }
                    }
                }
                break;

            case 'add_category':
                $category_name = sanitize_input($_POST['category_name']);
                $category_description = sanitize_input($_POST['category_description']);
                $category_color = sanitize_input($_POST['category_color']);
                $category_icon = sanitize_input($_POST['category_icon']);

                if (empty($category_name)) {
                    $message = "Please provide a category name.";
                    $message_type = "error";
                } else {
                    // Check if category name already exists
                    $check_query = "SELECT COUNT(*) as count FROM lms_material_categories WHERE name = ? AND status = 'active'";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "s", $category_name);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $check_row = mysqli_fetch_assoc($check_result);

                    if ($check_row['count'] > 0) {
                        $message = "A category with this name already exists.";
                        $message_type = "error";
                    } else {
                        $insert_query = "INSERT INTO lms_material_categories (name, description, icon, color, created_by, created_at)
                                        VALUES (?, ?, ?, ?, ?, NOW())";
                        $insert_stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($insert_stmt, "ssssi", $category_name, $category_description, $category_icon, $category_color, $_SESSION['user_id']);

                        if (mysqli_stmt_execute($insert_stmt)) {
                            $message = "Category created successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error creating category: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                }
                break;

            case 'delete_material':
                $material_id = (int)$_POST['material_id'];

                // Get file path before deleting
                $file_query = "SELECT file_path FROM lms_materials WHERE id = ? AND class_id = ? AND created_by = ?";
                $file_stmt = mysqli_prepare($conn, $file_query);
                mysqli_stmt_bind_param($file_stmt, "iii", $material_id, $class_id, $_SESSION['user_id']);
                mysqli_stmt_execute($file_stmt);
                $file_result = mysqli_stmt_get_result($file_stmt);
                $file_data = mysqli_fetch_assoc($file_result);

                $delete_query = "DELETE FROM lms_materials WHERE id = ? AND class_id = ? AND created_by = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "iii", $material_id, $class_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($delete_stmt)) {
                    // Delete physical file
                    if ($file_data && $file_data['file_path']) {
                        $full_path = '../uploads/materials/' . $file_data['file_path'];
                        if (file_exists($full_path)) {
                            unlink($full_path);
                        }
                    }
                    $message = "Material deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting material: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'toggle_visibility':
                $material_id = (int)$_POST['material_id'];
                $is_public = (int)$_POST['is_public'];

                $update_query = "UPDATE lms_materials SET is_public = ? WHERE id = ? AND class_id = ? AND created_by = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "iiii", $is_public, $material_id, $class_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($update_stmt)) {
                    $message = "Material visibility updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating material: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query for materials and lessons
$where_conditions = ["lm.class_id = ?"];
$params = [$class_id];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(lm.title LIKE ? OR lm.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= 'ss';
}

if ($category_filter) {
    $where_conditions[] = "lm.category_id = ?";
    $params[] = $category_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination (materials only)
$count_query = "SELECT COUNT(*) as total FROM lms_materials lm $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// Add lessons count
$lessons_count_query = "SELECT COUNT(*) as total FROM lessons l
                        JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                        WHERE lca.class_id = ? AND l.teacher_id = ?";
$lessons_count_stmt = mysqli_prepare($conn, $lessons_count_query);
mysqli_stmt_bind_param($lessons_count_stmt, "ii", $class_id, $class_data['teacher_id']);
mysqli_stmt_execute($lessons_count_stmt);
$lessons_count_result = mysqli_stmt_get_result($lessons_count_stmt);
$lessons_count = mysqli_fetch_assoc($lessons_count_result)['total'];

$total_records += $lessons_count;
$total_pages = ceil($total_records / $per_page);

// Get materials
$materials_query = "SELECT lm.*, mc.name as category_name, u.first_name, u.last_name, 'material' as type
                    FROM lms_materials lm
                    JOIN lms_material_categories mc ON lm.category_id = mc.id
                    JOIN users u ON lm.created_by = u.id
                    $where_clause
                    ORDER BY lm.created_at DESC
                    LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$materials_stmt = mysqli_prepare($conn, $materials_query);
mysqli_stmt_bind_param($materials_stmt, $param_types, ...$params);
mysqli_stmt_execute($materials_stmt);
$materials_result = mysqli_stmt_get_result($materials_stmt);

// Get lessons for this class
$lessons_query = "SELECT l.*, f.first_name, f.last_name, l.teacher_id as created_by, 'lesson' as type
                  FROM lessons l
                  JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                  JOIN faculty f ON l.teacher_id = f.id
                  WHERE lca.class_id = ? AND l.teacher_id = ?";
if ($search) {
    $lessons_query .= " AND (l.title LIKE ? OR l.description LIKE ?)";
}
$lessons_query .= " ORDER BY l.created_at DESC";

$lessons_params = [$class_id, $class_data['teacher_id']];
$lessons_param_types = "ii";
if ($search) {
    $lessons_params[] = "%$search%";
    $lessons_params[] = "%$search%";
    $lessons_param_types .= "ss";
}

$lessons_stmt = mysqli_prepare($conn, $lessons_query);
mysqli_stmt_bind_param($lessons_stmt, $lessons_param_types, ...$lessons_params);
mysqli_stmt_execute($lessons_stmt);
$lessons_result = mysqli_stmt_get_result($lessons_stmt);

// Combine materials and lessons
$all_items = [];
while ($material = mysqli_fetch_assoc($materials_result)) {
    $all_items[] = $material;
}
while ($lesson = mysqli_fetch_assoc($lessons_result)) {
    $all_items[] = $lesson;
}

// Apply pagination to combined results
$all_items = array_slice($all_items, $offset, $per_page);

// Filter items based on search and category
$filtered_items = array_filter($all_items, function($item) use ($search, $category_filter) {
    if ($search) {
        $searchLower = strtolower($search);
        $titleMatch = stripos($item['title'], $search) !== false;
        $descMatch = stripos($item['description'] ?? '', $search) !== false;
        if (!$titleMatch && !$descMatch) {
            return false;
        }
    }

    if ($category_filter) {
        if ($category_filter === 'lesson') {
            return $item['type'] === 'lesson';
        } else {
            return $item['type'] === 'material' && $item['category_id'] == $category_filter;
        }
    }

    return true;
});

// Get statistics
$stats_query = "SELECT
                COUNT(*) as total_materials,
                COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_materials,
                COUNT(CASE WHEN is_public = 0 THEN 1 END) as private_materials,
                SUM(file_size) as total_size
                FROM lms_materials
                WHERE class_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $class_id);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

// Get lessons statistics
$lessons_stats_query = "SELECT
                        COUNT(*) as total_lessons,
                        COUNT(CASE WHEN status = 'published' THEN 1 END) as published_lessons,
                        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_lessons
                        FROM lessons l
                        JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                        WHERE lca.class_id = ? AND l.teacher_id = ?";
$lessons_stats_stmt = mysqli_prepare($conn, $lessons_stats_query);
mysqli_stmt_bind_param($lessons_stats_stmt, "ii", $class_id, $class_data['teacher_id']);
mysqli_stmt_execute($lessons_stats_stmt);
$lessons_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($lessons_stats_stmt));

// Combine statistics
$stats['total_items'] = ($stats['total_materials'] ?? 0) + ($lessons_stats['total_lessons'] ?? 0);
$stats['total_lessons'] = $lessons_stats['total_lessons'] ?? 0;
$stats['published_lessons'] = $lessons_stats['published_lessons'] ?? 0;
$stats['draft_lessons'] = $lessons_stats['draft_lessons'] ?? 0;

// Get unique categories for filter
$categories_query = "SELECT DISTINCT mc.id, mc.name
                    FROM lms_material_categories mc
                    JOIN lms_materials lm ON mc.id = lm.category_id
                    WHERE lm.class_id = ? AND mc.status = 'active'
                    ORDER BY mc.name";
$categories_stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($categories_stmt, "i", $class_id);
mysqli_stmt_execute($categories_stmt);
$categories_result = mysqli_stmt_get_result($categories_stmt);

$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}

// Add lesson types as categories
$categories[] = ['id' => 'lesson', 'name' => 'Lesson'];

// Include the LMS header
include 'includes/lms_header.php'; ?>

<style>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.break-words {
  word-wrap: break-word;
  word-break: break-word;
}

.whitespace-nowrap {
  white-space: nowrap;
}

.min-w-0 {
  min-width: 0;
}

.flex-shrink-0 {
  flex-shrink: 0;
}

.flex-1 {
  flex: 1;
}
</style>
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Class Materials</h1>
            <p class="text-gray-600 mt-1">Manage learning materials for <?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <button onclick="openUploadModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-upload mr-2"></i>Upload Material
            </button>
            <button onclick="openCategoryModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-folder-plus mr-2"></i>Manage Categories
            </button>
            <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 sm:gap-4 sm:gap-6 mb-6">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center min-w-0 flex-1">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-file-alt text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Items</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_items'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center min-w-0 flex-1">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-eye text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Public Materials</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['public_materials'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center min-w-0 flex-1">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-lock text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Private Materials</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['private_materials'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center min-w-0 flex-1">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-hdd text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Size</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo formatFileSize($stats['total_size'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center min-w-0 flex-1">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-book text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Lessons</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_lessons'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">

        <div class="min-w-0 flex-1">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                   placeholder="Search materials...">
        </div>

        <div class="min-w-0 flex-1">
            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select id="category" name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                <?php
                $category_name = $category['name'];
                $category_id = $category['id'];
                ?>
                <option value="<?php echo htmlspecialchars($category_id); ?>" <?php echo $category_filter == $category_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category_name); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
        </div>

        <div class="flex items-end">
            <a href="?class_id=<?php echo $class_id; ?>" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition text-center">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
        </div>
    </form>
</div>

<!-- Materials Grid -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (empty($filtered_items)): ?>
    <div class="p-8 text-center">
        <i class="fas fa-file-alt text-gray-300 text-4xl mb-4"></i>
        <p class="text-gray-500 mb-4">No materials found.</p>
        <button onclick="openUploadModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
            Upload Your First Material
        </button>
    </div>
    <?php else: ?>
    <div class="p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
            <?php foreach ($filtered_items as $item): ?>
            <div class="bg-gray-50 rounded-lg p-4 hover:shadow-md transition-shadow min-h-[200px] flex flex-col overflow-hidden">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center min-w-0 flex-1">
                        <div class="w-10 h-10 rounded-lg <?php echo $item['type'] === 'lesson' ? 'bg-gradient-to-br from-indigo-500 to-purple-600' : 'bg-seait-orange'; ?> flex items-center justify-center mr-3">
                            <i class="fas <?php echo $item['type'] === 'lesson' ? 'fa-graduation-cap' : getFileIconByExtension(pathinfo($item['file_name'], PATHINFO_EXTENSION)); ?> text-white"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-medium text-gray-900 truncate" title="<?php echo htmlspecialchars($item['title']); ?>">
                                <?php echo htmlspecialchars($item['title']); ?>
                                <?php if ($item['type'] === 'lesson'): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 ml-1 border border-indigo-200">
                                        <i class="fas fa-book-open mr-1"></i>Lesson
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <p class="text-xs text-gray-500">
                                <?php if ($item['type'] === 'lesson'): ?>
                                    <i class="fas fa-clock mr-1 text-indigo-500"></i><?php echo ucfirst($item['status'] ?? 'draft'); ?>
                                    <?php if (isset($item['order_number']) && $item['order_number'] > 0): ?>
                                        <span class="ml-2 bg-indigo-200 text-indigo-800 px-1.5 py-0.5 rounded text-xs">
                                            Lesson <?php echo $item['order_number']; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i class="fas fa-file mr-1"></i><?php echo formatFileSize($item['file_size'] ?? 0); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-1">
                        <?php if ($item['type'] === 'material' && ($item['is_public'] ?? false)): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-eye mr-1"></i>Public
                        </span>
                        <?php elseif ($item['type'] === 'material' && !($item['is_public'] ?? false)): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            <i class="fas fa-lock mr-1"></i>Private
                        </span>
                        <?php elseif ($item['type'] === 'lesson'): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $item['status'] === 'published' ? 'bg-green-100 text-green-800 border border-green-200' : ($item['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : 'bg-gray-100 text-gray-800 border border-gray-200'); ?>">
                            <i class="fas <?php echo $item['status'] === 'published' ? 'fa-check-circle' : ($item['status'] === 'draft' ? 'fa-edit' : 'fa-archive'); ?> mr-1"></i>
                            <?php echo ucfirst($item['status'] ?? 'draft'); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($item['description']): ?>
                <div class="mb-3">
                    <p class="text-sm text-gray-600 line-clamp-3 break-words">
                        <i class="fas fa-info-circle mr-1 text-indigo-500"></i>
                        <?php echo htmlspecialchars($item['description']); ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                    <div class="flex items-center min-w-0 flex-1">
                        <i class="fas <?php echo $item['type'] === 'lesson' ? 'fa-user-graduate' : 'fa-folder'; ?> mr-1"></i>
                        <span><?php echo $item['type'] === 'lesson' ? 'Lesson Content' : htmlspecialchars($item['category_name']); ?></span>
                    </div>
                    <div class="flex items-center min-w-0 flex-1">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span><?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex space-x-2">
                        <?php if ($item['type'] === 'material' && $item['file_path']): ?>
                        <a href="<?php echo $item['file_path']; ?>" target="_blank"
                           class="inline-flex items-center px-3 py-1.5 bg-seait-orange text-white text-xs rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-download mr-1"></i>Download
                        </a>

                        <?php
                        // Check if file content can be displayed
                        $file_extension = pathinfo($item['file_name'], PATHINFO_EXTENSION);
                        $can_display = canDisplayContent($item['mime_type'], $file_extension);
                        if ($can_display):
                        ?>
                        <button onclick="viewFileContent('<?php echo htmlspecialchars($item['title']); ?>', '<?php echo $item['file_path']; ?>', '<?php echo $can_display; ?>', '<?php echo htmlspecialchars($item['file_name']); ?>')"
                                class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs rounded-md hover:bg-green-700 transition">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <?php endif; ?>

                        <?php elseif ($item['type'] === 'lesson'): ?>
                        <a href="view-lesson.php?id=<?php echo $item['id']; ?>"
                           class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-md hover:bg-indigo-700 transition">
                            <i class="fas fa-book-open mr-1"></i>View
                        </a>
                        <?php endif; ?>

                        <?php if ($item['type'] === 'material'): ?>
                        <button onclick="viewMaterial(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['title']); ?>', '<?php echo htmlspecialchars($item['description']); ?>', '<?php echo htmlspecialchars($item['category_name']); ?>', <?php echo $item['is_public'] ? 1 : 0; ?>, '<?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>', '<?php echo date('M j, Y g:i A', strtotime($item['created_at'])); ?>', '<?php echo htmlspecialchars($item['file_name']); ?>', '<?php echo formatFileSize($item['file_size']); ?>')"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs rounded-md hover:bg-blue-700 transition">
                            <i class="fas fa-info-circle mr-1"></i>Details
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if (($item['type'] === 'material' && $item['created_by'] == $_SESSION['user_id']) || ($item['type'] === 'lesson' && $item['created_by'] == $class_data['teacher_id'])): ?>
                    <div class="flex space-x-1">
                        <?php if ($item['type'] === 'material'): ?>
                        <button onclick="toggleVisibility(<?php echo $item['id']; ?>, <?php echo $item['is_public'] ? 0 : 1; ?>)"
                                class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-md hover:bg-yellow-200 transition" title="<?php echo $item['is_public'] ? 'Make Private' : 'Make Public'; ?>">
                            <i class="fas <?php echo $item['is_public'] ? 'fa-lock' : 'fa-eye'; ?>"></i>
                        </button>
                        <button onclick="deleteMaterial(<?php echo $item['id']; ?>)"
                                class="inline-flex items-center px-2 py-1 bg-red-100 text-red-800 text-xs rounded-md hover:bg-red-200 transition" title="Delete Material">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php elseif ($item['type'] === 'lesson'): ?>
                        <a href="edit-lesson.php?id=<?php echo $item['id']; ?>"
                           class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs rounded-md hover:bg-green-200 transition" title="Edit Lesson">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="view-lesson.php?id=<?php echo $item['id']; ?>"
                           class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-md hover:bg-blue-200 transition" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Upload Material Modal -->
<div id="uploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Upload Material</h3>
                <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload_material">

                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                    <input type="text" id="title" name="title" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="min-w-0 flex-1">
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                        <select id="category_id" name="category_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Category</option>
                            <?php
                            // Get all active material categories
                            $all_categories_query = "SELECT id, name, description FROM lms_material_categories WHERE status = 'active' ORDER BY name";
                            $all_categories_result = mysqli_query($conn, $all_categories_query);
                            while ($category = mysqli_fetch_assoc($all_categories_result)) {
                                echo "<option value='" . htmlspecialchars($category['id']) . "'>" . htmlspecialchars($category['name']);
                                if ($category['description']) {
                                    echo " - " . htmlspecialchars($category['description']);
                                }
                                echo "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="flex items-center min-w-0 flex-1">
                        <input type="checkbox" id="is_public" name="is_public" class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                        <label for="is_public" class="ml-2 block text-sm text-gray-900">Make public to students</label>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="file" class="block text-sm font-medium text-gray-700 mb-2">File *</label>
                    <input type="file" id="file" name="file" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <p class="text-xs text-gray-500 mt-1">Maximum file size: 50MB. Allowed types: PDF, DOC, PPT, XLS, TXT, Images, Videos, Audio, Archives</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeUploadModal()"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Upload Material
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View File Content Modal -->
<div id="viewContentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-5/6 lg:w-4/5 xl:w-3/4 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="contentModalTitle">View File Content</h3>
                <button onclick="closeViewContentModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="contentModalBody" class="max-h-screen overflow-y-auto">
                <!-- Loading indicator -->
                <div id="contentLoading" class="hidden text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-seait-orange"></div>
                    <p class="mt-2 text-gray-600">Loading content...</p>
                </div>
                <!-- Content will be loaded here -->
            </div>

            <div class="flex justify-end pt-4">
                <button onclick="closeViewContentModal()"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
// Set PDF.js worker path
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<script>
function openUploadModal() {
    document.getElementById('uploadModal').classList.remove('hidden');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    document.getElementById('uploadForm').reset();
}

function viewMaterial(id, title, description, category, isPublic, author, createdAt, fileName, fileSize) {
    const publicStatus = isPublic == 1 ?
        '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fas fa-eye mr-1"></i>Public</span>' :
        '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><i class="fas fa-lock mr-1"></i>Private</span>';

    const detailsHtml = `
        <div class="space-y-4">
            <div class="min-w-0 flex-1">
                <h4 class="text-lg font-semibold text-gray-900">${title}</h4>
                <div class="flex items-center mt-2 space-x-4">
                    ${publicStatus}
                    <span class="text-sm text-gray-500">${category || 'Uncategorized'}</span>
                </div>
            </div>

            ${description ? `
            <div class="min-w-0 flex-1">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Description:</h5>
                <div class="bg-gray-50 p-4 rounded-lg text-sm text-gray-800">
                    ${description}
                </div>
            </div>
            ` : ''}

            <div class="border-t pt-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="min-w-0 flex-1">
                        <span class="font-medium text-gray-700">File Name:</span>
                        <span class="text-gray-900 ml-2">${fileName}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="font-medium text-gray-700">File Size:</span>
                        <span class="text-gray-900 ml-2">${fileSize}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="font-medium text-gray-700">Author:</span>
                        <span class="text-gray-900 ml-2">${author}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="font-medium text-gray-700">Uploaded:</span>
                        <span class="text-gray-900 ml-2">${createdAt}</span>
                    </div>
                </div>
            </div>
        </div>
    `;

    alert('Material Details:\n\n' + title + '\n\n' + (description || 'No description') + '\n\nFile: ' + fileName + '\nSize: ' + fileSize + '\nAuthor: ' + author + '\nUploaded: ' + createdAt);
}

function toggleVisibility(id, isPublic) {
    const action = isPublic ? 'make public' : 'make private';
    if (confirm(`Are you sure you want to ${action} this material?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_visibility">
            <input type="hidden" name="material_id" value="${id}">
            <input type="hidden" name="is_public" value="${isPublic}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteMaterial(id) {
    if (confirm('Are you sure you want to delete this material? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_material">
            <input type="hidden" name="material_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewFileContent(title, filePath, displayType, fileName) {
    const modalBody = document.getElementById('contentModalBody');
    const modalTitle = document.getElementById('contentModalTitle');
    const contentLoading = document.getElementById('contentLoading');
    modalTitle.textContent = `View File Content: ${title}`;
    contentLoading.classList.remove('hidden');
    modalBody.innerHTML = ''; // Clear previous content

    // Show modal first
    document.getElementById('viewContentModal').classList.remove('hidden');

    if (displayType === 'text') {
        fetch(filePath)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                modalBody.innerHTML = `<pre style="white-space: pre-wrap; word-wrap: break-word; font-family: monospace; background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; max-height: 70vh; overflow-y: auto;">${text}</pre>`;
                contentLoading.classList.add('hidden');
            })
            .catch(error => {
                modalBody.innerHTML = `<div class="text-center py-8"><p class="text-red-600 mb-2">Error reading file content:</p><p class="text-gray-600">${error.message}</p><p class="text-sm text-gray-500 mt-2">The file may be too large or not accessible.</p></div>`;
                contentLoading.classList.add('hidden');
            });
    } else if (displayType === 'image') {
        const img = new Image();
        img.onload = function() {
            modalBody.innerHTML = `<div class="text-center"><img src="${filePath}" alt="${title}" style="max-width: 100%; height: auto; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);"></div>`;
            contentLoading.classList.add('hidden');
        };
        img.onerror = function() {
            modalBody.innerHTML = `<div class="text-center py-8"><p class="text-red-600">Error loading image</p><p class="text-sm text-gray-500 mt-2">The image file may be corrupted or not accessible.</p></div>`;
            contentLoading.classList.add('hidden');
        };
        img.src = filePath;
    } else if (displayType === 'video') {
        modalBody.innerHTML = `<div class="text-center"><video controls style="max-width: 100%; height: auto; border-radius: 0.5rem;"><source src="${filePath}" type="video/mp4">Your browser does not support the video tag.</video></div>`;
        contentLoading.classList.add('hidden');
    } else if (displayType === 'audio') {
        modalBody.innerHTML = `<div class="text-center"><audio controls style="width: 100%;"><source src="${filePath}" type="audio/mpeg">Your browser does not support the audio tag.</audio></div>`;
        contentLoading.classList.add('hidden');
    } else if (displayType === 'pdf') {
        // Use PDF.js for better PDF viewing
        modalBody.innerHTML = `
            <div class="text-center">
                <div class="mb-4 flex justify-center space-x-2">
                    <button onclick="loadPdfWithPdfJs('${filePath}')" class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-eye mr-2"></i>Enhanced PDF Viewer
                    </button>
                    <a href="${filePath}" target="_blank" class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>Open in New Tab
                    </a>
                </div>
                <div id="pdfViewer" class="border rounded-lg">
                    <iframe src="${filePath}#toolbar=1&navpanes=1&scrollbar=1" style="width: 100%; height: 70vh; border: none; border-radius: 0.5rem;"></iframe>
                </div>
            </div>`;
        contentLoading.classList.add('hidden');
    } else if (displayType === 'document' || displayType === 'spreadsheet' || displayType === 'presentation') {
        // For Office documents, try multiple viewer options
        const fullUrl = window.location.origin + '/' + filePath.replace('../', '');
        const encodedUrl = encodeURIComponent(fullUrl);

        // Multiple viewer options
        const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodedUrl}`;
        const googleViewerUrl = `https://docs.google.com/viewer?url=${encodedUrl}&embedded=true`;
        const office365ViewerUrl = `https://view.officeapps.live.com/op/view.aspx?src=${encodedUrl}`;

        modalBody.innerHTML = `
            <div class="text-center">
                <div class="mb-4 flex flex-wrap justify-center gap-2">
                    <button onclick="switchToOfficeViewer('${officeViewerUrl}')" class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-file-word mr-2"></i>Microsoft Viewer
                    </button>
                    <button onclick="switchToGoogleViewer('${googleViewerUrl}')" class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition">
                        <i class="fab fa-google mr-2"></i>Google Viewer
                    </button>
                    <button onclick="switchToOffice365Viewer('${office365ViewerUrl}')" class="px-3 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>Office 365
                    </button>
                    <a href="${filePath}" target="_blank" class="px-3 py-2 bg-orange-600 text-white text-sm rounded-md hover:bg-orange-700 transition">
                        <i class="fas fa-download mr-2"></i>Download
                    </a>
                    <button onclick="openFileDirectly('${filePath}')" class="px-3 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>Open Directly
                    </button>
                </div>
                <div id="documentViewer" class="border rounded-lg">
                    <div id="documentLoading" class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-seait-orange"></div>
                        <p class="mt-2 text-gray-600">Loading Microsoft Office viewer...</p>
                        <p class="text-xs text-gray-500 mt-2">If this doesn't work, try the other viewer options above.</p>
                    </div>
                    <iframe id="documentIframe" src="${officeViewerUrl}" style="width: 100%; height: 70vh; border: none; border-radius: 0.5rem; display: none;" frameborder="0" onload="hideDocumentLoading()" onerror="showViewerError()"></iframe>
                </div>
                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-2">If you see an error message, try the other viewer options or download the file.</p>
                    <p class="text-xs text-gray-500">Note: Office document viewers may not work with all file types or configurations.</p>
                    <div id="viewerError" class="hidden mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-700 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Viewer Error</p>
                        <p class="text-xs text-red-600">The document viewer encountered an error. Please try:</p>
                        <ul class="text-xs text-red-600 mt-1 ml-4 list-disc">
                            <li>Switching to a different viewer option</li>
                            <li>Downloading the file to view locally</li>
                            <li>Opening the file directly in a new tab</li>
                        </ul>
                    </div>
                </div>
            </div>`;
        contentLoading.classList.add('hidden');

        // Set a timeout to show the iframe even if onload doesn't fire
        setTimeout(function() {
            const iframe = document.getElementById('documentIframe');
            const loading = document.getElementById('documentLoading');
            if (iframe && loading) {
                loading.style.display = 'none';
                iframe.style.display = 'block';
            }
        }, 8000); // 8 second timeout
    } else {
        modalBody.innerHTML = `<div class="text-center py-8"><p class="text-gray-600">No specific viewer available for this file type.</p><p class="text-sm text-gray-500 mt-2">File: ${fileName}</p><p class="text-sm text-gray-500">You can <a href="${filePath}" target="_blank" class="text-blue-600 hover:text-blue-800">download the file</a> to view it in your preferred application.</p></div>`;
        contentLoading.classList.add('hidden');
    }
}

function closeViewContentModal() {
    document.getElementById('viewContentModal').classList.add('hidden');
    document.getElementById('contentModalBody').innerHTML = ''; // Clear content
    document.getElementById('contentLoading').classList.add('hidden'); // Hide loading indicator
}

function loadPdfWithPdfJs(pdfUrl) {
    const pdfViewer = document.getElementById('pdfViewer');
    const contentLoading = document.getElementById('contentLoading');

    contentLoading.classList.remove('hidden');
    pdfViewer.innerHTML = '';

    // Load PDF using PDF.js
    pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
        const numPages = pdf.numPages;
        let currentPage = 1;

        function renderPage(pageNum) {
            pdf.getPage(pageNum).then(function(page) {
                const viewport = page.getViewport({scale: 1.5});
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };

                page.render(renderContext).promise.then(function() {
                    pdfViewer.innerHTML = `
                        <div class="text-center">
                            <div class="mb-4 flex justify-center items-center space-x-4">
                                <button onclick="changePage(${pageNum - 1})" ${pageNum <= 1 ? 'disabled' : ''} class="px-3 py-2 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-chevron-left mr-2"></i>Previous
                                </button>
                                <span class="text-sm text-gray-600">Page ${pageNum} of ${numPages}</span>
                                <button onclick="changePage(${pageNum + 1})" ${pageNum >= numPages ? 'disabled' : ''} class="px-3 py-2 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    Next<i class="fas fa-chevron-right ml-2"></i>
                                </button>
                            </div>
                            <div class="border rounded-lg p-4 bg-white">
                                <canvas id="pdfCanvas" style="max-width: 100%; height: auto;"></canvas>
                            </div>
                        </div>`;

                    const canvasContainer = pdfViewer.querySelector('#pdfCanvas');
                    canvasContainer.appendChild(canvas);
                    contentLoading.classList.add('hidden');
                });
            });
        }

        // Store PDF and current page globally for navigation
        window.currentPdf = pdf;
        window.currentPage = 1;
        window.totalPages = numPages;
        window.renderPage = renderPage;

        renderPage(1);
    }).catch(function(error) {
        pdfViewer.innerHTML = `<div class="text-center py-8"><p class="text-red-600">Error loading PDF: ${error.message}</p></div>`;
        contentLoading.classList.add('hidden');
    });
}

function changePage(newPage) {
    if (window.currentPdf && newPage >= 1 && newPage <= window.totalPages) {
        window.currentPage = newPage;
        window.renderPage(newPage);
    }
}

function switchToOfficeViewer(url) {
    const documentIframe = document.getElementById('documentIframe');
    const contentLoading = document.getElementById('documentLoading');
    const viewerError = document.getElementById('viewerError');

    if (documentIframe) {
        contentLoading.classList.remove('hidden');
        documentIframe.style.display = 'none';
        viewerError.classList.add('hidden'); // Hide previous error

        documentIframe.onload = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
        };

        documentIframe.onerror = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
            viewerError.classList.remove('hidden'); // Show error
            alert('Error loading Microsoft viewer. Please try the Google viewer or download the file.');
        };

        documentIframe.src = url;
    }
}

function switchToGoogleViewer(url) {
    const documentIframe = document.getElementById('documentIframe');
    const contentLoading = document.getElementById('documentLoading');
    const viewerError = document.getElementById('viewerError');

    if (documentIframe) {
        contentLoading.classList.remove('hidden');
        documentIframe.style.display = 'none';
        viewerError.classList.add('hidden'); // Hide previous error

        documentIframe.onload = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
        };

        documentIframe.onerror = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
            viewerError.classList.remove('hidden'); // Show error
            alert('Error loading Google viewer. Please try the Microsoft viewer or download the file.');
        };

        documentIframe.src = url;
    }
}

function switchToOffice365Viewer(url) {
    const documentIframe = document.getElementById('documentIframe');
    const contentLoading = document.getElementById('documentLoading');
    const viewerError = document.getElementById('viewerError');

    if (documentIframe) {
        contentLoading.classList.remove('hidden');
        documentIframe.style.display = 'none';
        viewerError.classList.add('hidden'); // Hide previous error

        documentIframe.onload = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
        };

        documentIframe.onerror = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
            viewerError.classList.remove('hidden'); // Show error
            alert('Error loading Office 365 viewer. Please try the Microsoft viewer or download the file.');
        };

        documentIframe.src = url;
    }
}

function openFileDirectly(filePath) {
    window.open(filePath, '_blank');
}

function showViewerError() {
    const viewerError = document.getElementById('viewerError');
    if (viewerError) {
        viewerError.classList.remove('hidden');
    }
}

function hideDocumentLoading() {
    const documentLoading = document.getElementById('documentLoading');
    const documentIframe = document.getElementById('documentIframe');

    if (documentLoading) {
        documentLoading.style.display = 'none';
    }
    if (documentIframe) {
        documentIframe.style.display = 'block';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const uploadModal = document.getElementById('uploadModal');
    const viewContentModal = document.getElementById('viewContentModal');

    if (event.target === uploadModal) {
        closeUploadModal();
    }
    if (event.target === viewContentModal) {
        closeViewContentModal();
    }
}
</script>

<!-- Category Management Modal -->
<div id="categoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Manage Material Categories</h3>
                <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mb-6">
                <h4 class="text-md font-medium text-gray-700 mb-3">Available Categories</h4>
                <div class="bg-gray-50 rounded-lg p-4 max-h-60 overflow-y-auto">
                    <?php
                    $all_categories_query = "SELECT id, name, description, icon, color FROM lms_material_categories WHERE status = 'active' ORDER BY name";
                    $all_categories_result = mysqli_query($conn, $all_categories_query);
                    if (mysqli_num_rows($all_categories_result) > 0):
                    ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php while ($category = mysqli_fetch_assoc($all_categories_result)): ?>
                        <div class="flex items-center p-3 bg-white rounded-lg border">
                            <div class="w-8 h-8 rounded mr-3 flex items-center justify-center" style="background-color: <?php echo $category['color']; ?>">
                                <i class="<?php echo $category['icon']; ?> text-white text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                                <?php if ($category['description']): ?>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($category['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No categories available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="border-t pt-4">
                <h4 class="text-md font-medium text-gray-700 mb-3">Create New Category</h4>
                <form method="POST" id="categoryForm">
                    <input type="hidden" name="action" value="add_category">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="min-w-0 flex-1">
                            <label for="category_name" class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                            <input type="text" id="category_name" name="category_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div class="min-w-0 flex-1">
                            <label for="category_color" class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                            <input type="color" id="category_color" name="category_color" value="#3B82F6"
                                   class="w-full h-10 border border-gray-300 rounded-lg">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="category_description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="category_description" name="category_description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="category_icon" class="block text-sm font-medium text-gray-700 mb-2">Icon</label>
                        <select id="category_icon" name="category_icon"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="fas fa-folder">📁 Folder</option>
                            <option value="fas fa-file-alt">📄 Document</option>
                            <option value="fas fa-book">📚 Book</option>
                            <option value="fas fa-video">🎥 Video</option>
                            <option value="fas fa-music">🎵 Audio</option>
                            <option value="fas fa-image">🖼️ Image</option>
                            <option value="fas fa-chalkboard-teacher">👨‍🏫 Lecture</option>
                            <option value="fas fa-tasks">✅ Assignment</option>
                            <option value="fas fa-link">🔗 Link</option>
                            <option value="fas fa-download">⬇️ Download</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeCategoryModal()"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                            Create Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('categoryModal').classList.remove('hidden');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
    document.getElementById('categoryForm').reset();
}
</script>

<?php
// Helper functions
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function getFileIcon($mime_type) {
    $type = strtolower($mime_type);

    if (strpos($type, 'pdf') !== false) return 'fa-file-pdf';
    if (strpos($type, 'word') !== false || strpos($type, 'doc') !== false) return 'fa-file-word';
    if (strpos($type, 'powerpoint') !== false || strpos($type, 'ppt') !== false) return 'fa-file-powerpoint';
    if (strpos($type, 'excel') !== false || strpos($type, 'sheet') !== false || strpos($type, 'xls') !== false) return 'fa-file-excel';
    if (strpos($type, 'image') !== false) return 'fa-file-image';
    if (strpos($type, 'video') !== false) return 'fa-file-video';
    if (strpos($type, 'audio') !== false) return 'fa-file-audio';
    if (strpos($type, 'zip') !== false || strpos($type, 'rar') !== false || strpos($type, 'archive') !== false) return 'fa-file-archive';
    if (strpos($type, 'text') !== false || strpos($type, 'txt') !== false) return 'fa-file-alt';

    return 'fa-file';
}

function getFileIconByExtension($file_extension) {
    $extension = strtolower($file_extension);

    switch ($extension) {
        case 'pdf':
            return 'fa-file-pdf';
        case 'doc':
        case 'docx':
        case 'rtf':
            return 'fa-file-word';
        case 'ppt':
        case 'pptx':
            return 'fa-file-powerpoint';
        case 'xls':
        case 'xlsx':
        case 'csv':
            return 'fa-file-excel';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'bmp':
        case 'svg':
        case 'webp':
        case 'tiff':
        case 'ico':
            return 'fa-file-image';
        case 'mp4':
        case 'avi':
        case 'mov':
        case 'wmv':
        case 'flv':
        case 'webm':
        case 'mkv':
        case '3gp':
        case 'm4v':
            return 'fa-file-video';
        case 'mp3':
        case 'wav':
        case 'ogg':
        case 'aac':
        case 'flac':
        case 'm4a':
        case 'wma':
            return 'fa-file-audio';
        case 'zip':
        case 'rar':
        case '7z':
        case 'tar':
        case 'gz':
            return 'fa-file-archive';
        case 'txt':
        case 'md':
        case 'html':
        case 'htm':
        case 'css':
        case 'js':
        case 'json':
        case 'xml':
        case 'log':
            return 'fa-file-alt';
        default:
            return 'fa-file';
    }
}

function canDisplayContent($file_type, $file_extension) {
    $displayable_types = [
        'pdf' => ['pdf'],
        'text' => ['txt', 'md', 'html', 'htm', 'css', 'js', 'json', 'xml', 'csv', 'log'],
        'document' => ['doc', 'docx', 'rtf'],
        'spreadsheet' => ['xls', 'xlsx', 'csv'],
        'presentation' => ['ppt', 'pptx'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'tiff', 'ico'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp', 'm4v'],
        'audio' => ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a', 'wma']
    ];

    $file_extension = strtolower($file_extension);

    foreach ($displayable_types as $type => $extensions) {
        if (in_array($file_extension, $extensions)) {
            return $type;
        }
    }

    return false;
}

include 'includes/lms_footer.php';
?>