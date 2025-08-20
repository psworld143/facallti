<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$class_id) {
    header('Location: my-classes.php');
    exit();
}

// Get student_id for verification
$student_id = get_student_id($conn, $_SESSION['email']);

// Verify student is enrolled in this class
$class_query = "SELECT ce.*, tc.section, tc.join_code, tc.status as class_status,
                cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description,
                f.id as teacher_id, f.first_name as teacher_first_name, f.last_name as teacher_last_name,
                f.email as teacher_email
                FROM class_enrollments ce
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
                WHERE ce.class_id = ? AND ce.student_id = ? AND ce.status = 'enrolled'";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "ii", $class_id, $student_id);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: my-classes.php');
    exit();
}

// Set page title
$page_title = 'Learning Materials - ' . $class_data['subject_title'];

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';

// Get material categories with material counts
$categories_query = "SELECT mc.id, mc.name, mc.description, mc.icon, mc.color,
                    COUNT(m.id) as material_count
                    FROM lms_material_categories mc
                    LEFT JOIN lms_materials m ON mc.id = m.category_id AND m.class_id = ? AND m.status = 'active'
                    WHERE mc.status = 'active'
                    GROUP BY mc.id
                    HAVING material_count > 0
                    ORDER BY mc.name";
$categories_stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($categories_stmt, "i", $class_id);
mysqli_stmt_execute($categories_stmt);
$categories_result = mysqli_stmt_get_result($categories_stmt);

// Get all materials for this class (we'll organize them by category in PHP)
$materials_query = "SELECT m.*, mc.name as category_name, mc.icon as category_icon, mc.color as category_color,
                   COUNT(ml.id) as access_count,
                   f.first_name as created_by_name, f.last_name as created_by_last_name
                   FROM lms_materials m
                   JOIN lms_material_categories mc ON m.category_id = mc.id
                   JOIN faculty f ON m.created_by = f.id
                   LEFT JOIN lms_material_access_logs ml ON m.id = ml.material_id
                   WHERE m.class_id = ? AND m.status = 'active'
                   GROUP BY m.id
                   ORDER BY mc.name, m.order_number, m.created_at DESC";

$materials_stmt = mysqli_prepare($conn, $materials_query);
mysqli_stmt_bind_param($materials_stmt, "i", $class_id);
mysqli_stmt_execute($materials_stmt);
$materials_result = mysqli_stmt_get_result($materials_stmt);

// Organize materials by category
$materials_by_category = [];
$all_materials = [];
while ($material = mysqli_fetch_assoc($materials_result)) {
    $category_id = $material['category_id'];
    if (!isset($materials_by_category[$category_id])) {
        $materials_by_category[$category_id] = [];
    }
    $materials_by_category[$category_id][] = $material;
    $all_materials[] = $material;
}

// Filter materials by search if provided
if ($search) {
    $filtered_materials_by_category = [];
    foreach ($materials_by_category as $category_id => $materials) {
        $filtered_materials = array_filter($materials, function($material) use ($search) {
            return stripos($material['title'], $search) !== false ||
                   stripos($material['description'], $search) !== false ||
                   stripos($material['category_name'], $search) !== false;
        });
        if (!empty($filtered_materials)) {
            $filtered_materials_by_category[$category_id] = array_values($filtered_materials);
        }
    }
    $materials_by_category = $filtered_materials_by_category;
}

// Get total material count (after filtering)
$total_materials = array_sum(array_map('count', $materials_by_category));

// Handle material access logging
if (isset($_GET['access_material'])) {
    $material_id = (int)$_GET['access_material'];

    // Log access
    $log_query = "INSERT INTO lms_material_access_logs (material_id, student_id, ip_address, user_agent) VALUES (?, ?, ?, ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    mysqli_stmt_bind_param($log_stmt, "iiss", $material_id, $student_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    mysqli_stmt_execute($log_stmt);

    // Redirect to material
    $material_query = "SELECT * FROM lms_materials WHERE id = ? AND class_id = ? AND status = 'active'";
    $material_stmt = mysqli_prepare($conn, $material_query);
    mysqli_stmt_bind_param($material_stmt, "ii", $material_id, $class_id);
    mysqli_stmt_execute($material_stmt);
    $material_result = mysqli_stmt_get_result($material_stmt);
    $material = mysqli_fetch_assoc($material_result);

    if ($material) {
        if ($material['type'] === 'url' && $material['external_url']) {
            header('Location: ' . $material['external_url']);
            exit();
        } elseif ($material['type'] === 'file' && $material['file_path']) {
            // Serve file download
            $file_path = '../uploads/materials/' . $material['file_path'];
            if (file_exists($file_path)) {
                header('Content-Type: ' . $material['mime_type']);
                header('Content-Disposition: inline; filename="' . $material['file_name'] . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit();
            }
        }
    }

    // If not a file or URL, redirect back to materials page
    header('Location: lms_materials.php?class_id=' . $class_id);
    exit();
}

// Get material statistics
$stats_query = "SELECT
                COUNT(DISTINCT m.id) as total_materials,
                COUNT(DISTINCT m.category_id) as total_categories,
                COUNT(DISTINCT ml.material_id) as accessed_materials,
                COUNT(ml.id) as total_accesses
                FROM lms_materials m
                LEFT JOIN lms_material_access_logs ml ON m.id = ml.material_id AND ml.student_id = ?
                WHERE m.class_id = ? AND m.status = 'active'";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "ii", $student_id, $class_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Include the shared LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Learning Materials</h1>
    <p class="text-sm sm:text-base text-gray-600">Access course materials and resources uploaded by your teacher for <?php echo htmlspecialchars($class_data['subject_title']); ?></p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-book text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Materials</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($total_materials); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-folder text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Categories</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format(mysqli_num_rows($categories_result)); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-eye text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Accessed</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['accessed_materials'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-download text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Downloads</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_accesses'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Teacher Info -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 sm:mb-8">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                <i class="fas fa-chalkboard-teacher text-white"></i>
            </div>
        </div>
        <div class="ml-3">
            <h4 class="text-sm font-medium text-blue-900">Course Instructor</h4>
            <p class="text-sm text-blue-700"><?php echo htmlspecialchars($class_data['teacher_first_name'] . ' ' . $class_data['teacher_last_name']); ?></p>
            <p class="text-xs text-blue-600">All materials are uploaded and managed by your teacher</p>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Search Materials</h3>
    </div>
    <div class="p-6">
        <form method="GET" class="flex flex-col sm:flex-row gap-4">
            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
            <div class="flex-1">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search materials..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
            </div>
            <div class="flex items-center space-x-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <?php if ($search): ?>
                <a href="lms_materials.php?class_id=<?php echo $class_id; ?>" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Materials by Category Tabs -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Course Materials (<?php echo $total_materials; ?>)</h3>
            <div class="text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Materials uploaded by <?php echo htmlspecialchars($class_data['teacher_first_name'] . ' ' . $class_data['teacher_last_name']); ?>
            </div>
        </div>
    </div>

    <?php
    $first_tab = true;
    $categories_with_materials = [];
    mysqli_data_seek($categories_result, 0);
    while ($category = mysqli_fetch_assoc($categories_result)) {
        if (isset($materials_by_category[$category['id']]) && !empty($materials_by_category[$category['id']])) {
            $categories_with_materials[] = $category;
        }
    }

    if (empty($categories_with_materials)): ?>
        <div class="py-4 text-gray-500">
            <?php if ($search): ?>
                <i class="fas fa-search text-gray-300 text-4xl mb-4"></i>
                <p class="text-gray-500">No materials found matching "<?php echo htmlspecialchars($search); ?>"
            <?php else: ?>
                <i class="fas fa-book text-gray-300 text-4xl mb-4"></i>
                <p class="text-gray-500">No materials have been uploaded yet.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Category Tabs -->
        <div class="border-b border-gray-200">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
                <?php foreach ($categories_with_materials as $category): ?>
                <button onclick="showCategoryWithScroll(<?php echo $category['id']; ?>)"
                        class="category-tab py-4 px-1 border-b-2 font-medium text-sm <?php echo $first_tab ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                        data-category="<?php echo $category['id']; ?>">
                    <div class="flex items-center">
                        <div class="w-5 h-5 rounded mr-2 flex items-center justify-center" style="background-color: <?php echo $category['color']; ?>">
                            <i class="<?php echo $category['icon']; ?> text-white text-xs"></i>
                        </div>
                        <?php echo htmlspecialchars($category['name']); ?>
                        <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                            <?php echo count($materials_by_category[$category['id']]); ?>
                        </span>
                    </div>
                </button>
                <?php $first_tab = false; endforeach; ?>
            </nav>
        </div>

        <!-- Materials Content -->
        <div class="p-6">
            <?php
            $first_content = true;
            foreach ($categories_with_materials as $category):
                $category_materials = $materials_by_category[$category['id']] ?? [];
            ?>
            <div id="category-<?php echo $category['id']; ?>"
                 class="category-content <?php echo $first_content ? '' : 'hidden'; ?>">
                <div class="mb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-2">
                        <i class="<?php echo $category['icon']; ?> mr-2" style="color: <?php echo $category['color']; ?>"></i>
                        <?php echo htmlspecialchars($category['name']); ?>
                        <?php if ($search): ?>
                            <span class="text-sm text-gray-500">(<?php echo count($category_materials); ?> results)</span>
                        <?php endif; ?>
                    </h4>
                    <?php if ($category['description']): ?>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($category['description']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="space-y-4">
                    <?php foreach ($category_materials as $material): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors material-item"
                         data-title="<?php echo htmlspecialchars(strtolower($material['title'])); ?>"
                         data-description="<?php echo htmlspecialchars(strtolower($material['description'])); ?>">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                         style="background-color: <?php echo $material['category_color']; ?>">
                                        <i class="<?php echo $material['category_icon']; ?> text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900">
                                            <a href="lms_materials.php?class_id=<?php echo $class_id; ?>&access_material=<?php echo $material['id']; ?>"
                                               class="hover:text-seait-orange transition-colors">
                                                <?php echo htmlspecialchars($material['title']); ?>
                                            </a>
                                        </h4>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($material['category_name']); ?></p>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-3"><?php echo htmlspecialchars(substr($material['description'], 0, 150)) . (strlen($material['description']) > 150 ? '...' : ''); ?></p>
                                <div class="flex items-center text-sm text-gray-500 space-x-4">
                                    <span class="flex items-center">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($material['created_by_name'] . ' ' . $material['created_by_last_name']); ?>
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?php echo date('M d, Y', strtotime($material['created_at'])); ?>
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-download mr-1"></i>
                                        <?php echo number_format($material['access_count']); ?> downloads
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-file mr-1"></i>
                                        <?php echo ucfirst($material['type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <a href="lms_materials.php?class_id=<?php echo $class_id; ?>&access_material=<?php echo $material['id']; ?>"
                                   class="inline-flex items-center px-3 py-2 bg-seait-orange text-white text-sm rounded-lg hover:bg-orange-600 transition">
                                    <i class="fas fa-download mr-2"></i>Access
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $first_content = false; endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function showCategory(categoryId) {
    // Hide all category contents
    const contents = document.querySelectorAll('.category-content');
    contents.forEach(content => {
        content.style.display = 'none';
        content.classList.add('hidden');
    });

    // Show selected category content
    const selectedContent = document.getElementById('category-' + categoryId);
    if (selectedContent) {
        selectedContent.style.display = 'block';
        selectedContent.classList.remove('hidden');
    }

    // Update tab styles
    const tabs = document.querySelectorAll('.category-tab');
    tabs.forEach(tab => {
        tab.classList.remove('border-seait-orange', 'text-seait-orange');
        tab.classList.add('border-transparent', 'text-gray-500');
    });

    // Highlight selected tab
    const selectedTab = document.querySelector(`[data-category="${categoryId}"]`);
    if (selectedTab) {
        selectedTab.classList.remove('border-transparent', 'text-gray-500');
        selectedTab.classList.add('border-seait-orange', 'text-seait-orange');
    }
}

// Add search highlighting functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput && searchInput.value.trim() !== '') {
        highlightSearchTerms(searchInput.value);
    }
});

function highlightSearchTerms(searchTerm) {
    if (!searchTerm) return;

    const searchLower = searchTerm.toLowerCase();
    const materialItems = document.querySelectorAll('.material-item');

    materialItems.forEach(item => {
        const title = item.querySelector('h4 a');
        const description = item.querySelector('p');

        if (title) {
            const titleText = title.textContent;
            const highlightedTitle = titleText.replace(
                new RegExp(searchTerm, 'gi'),
                match => `<mark class="bg-yellow-200 px-1 rounded">${match}</mark>`
            );
            title.innerHTML = highlightedTitle;
        }

        if (description) {
            const descText = description.textContent;
            const highlightedDesc = descText.replace(
                new RegExp(searchTerm, 'gi'),
                match => `<mark class="bg-yellow-200 px-1 rounded">${match}</mark>`
            );
            description.innerHTML = highlightedDesc;
        }
    });
}

// Add smooth scrolling for tab navigation
function scrollToTab(categoryId) {
    const tab = document.querySelector(`[data-category="${categoryId}"]`);
    if (tab) {
        tab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
}

// Enhanced showCategory with smooth scrolling
function showCategoryWithScroll(categoryId) {
    showCategory(categoryId);
    scrollToTab(categoryId);
}
</script>

<style>
.category-content {
    transition: opacity 0.3s ease-in-out;
}

.category-content.hidden {
    opacity: 0;
}

.category-tab {
    transition: all 0.2s ease-in-out;
    cursor: pointer;
}

.category-tab:hover {
    transform: translateY(-1px);
}

mark {
    animation: highlight 0.5s ease-in-out;
}

@keyframes highlight {
    0% { background-color: #fef3c7; }
    50% { background-color: #fde68a; }
    100% { background-color: #fef3c7; }
}

/* Responsive tab navigation */
@media (max-width: 768px) {
    .category-tab {
        font-size: 0.875rem;
        padding: 0.5rem 0.25rem;
    }

    .category-tab .flex {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .category-tab span {
        margin-top: 0.25rem;
        margin-left: 0;
    }
}
</style>

<?php
// Include the shared LMS footer
include 'includes/lms_footer.php';
?>