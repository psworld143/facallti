<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Manage Employee Degrees';

// Handle degree update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_degree') {
    header('Content-Type: application/json');
    
    $faculty_id = (int)$_POST['faculty_id'];
    $highest_education = sanitize_input($_POST['highest_education']);
    $field_of_study = sanitize_input($_POST['field_of_study']);
    $school_university = sanitize_input($_POST['school_university']);
    $year_graduated = isset($_POST['year_graduated']) ? (int)$_POST['year_graduated'] : null;

    // Validate faculty exists
    $check_query = "SELECT id FROM faculty WHERE id = ? AND is_active = 1";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $faculty_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Faculty member not found']);
        exit();
    }

    // Check if faculty_details record exists
    $details_check_query = "SELECT id FROM faculty_details WHERE faculty_id = ?";
    $details_check_stmt = mysqli_prepare($conn, $details_check_query);
    mysqli_stmt_bind_param($details_check_stmt, "i", $faculty_id);
    mysqli_stmt_execute($details_check_stmt);
    $details_check_result = mysqli_stmt_get_result($details_check_stmt);

    if (mysqli_num_rows($details_check_result) > 0) {
        // Update existing record
        $update_query = "UPDATE faculty_details SET 
            highest_education = ?, 
            field_of_study = ?, 
            school_university = ?, 
            year_graduated = ?,
            updated_at = NOW()
            WHERE faculty_id = ?";
        
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "sssii", 
            $highest_education, $field_of_study, $school_university, $year_graduated, $faculty_id
        );
    } else {
        // Insert new record
        $insert_query = "INSERT INTO faculty_details (
            faculty_id, highest_education, field_of_study, school_university, year_graduated, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "isssi", 
            $faculty_id, $highest_education, $field_of_study, $school_university, $year_graduated
        );
    }

    if (mysqli_stmt_execute($update_stmt ?? $insert_stmt)) {
        echo json_encode(['success' => true, 'message' => 'Degree information updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating degree information: ' . mysqli_error($conn)]);
    }
    exit();
}

// Get filters
$degree_filter = $_GET['degree'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for faculty with degree information
$where_conditions = ["f.is_active = 1"];
$params = [];
$param_types = "";

if (!empty($degree_filter)) {
    $where_conditions[] = "fd.highest_education = ?";
    $params[] = $degree_filter;
    $param_types .= "s";
}

if (!empty($search)) {
    $where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ? OR fd.field_of_study LIKE ? OR fd.school_university LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sssss";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM faculty f 
                LEFT JOIN faculty_details fd ON f.id = fd.faculty_id 
                WHERE $where_clause";

$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;
$total_pages = ceil($total_records / $records_per_page);

// Main query with pagination
$query = "SELECT 
    f.id,
    f.first_name,
    f.last_name,
    f.email,
    f.position,
    f.department,
    fd.highest_education,
    fd.field_of_study,
    fd.school_university,
    fd.year_graduated,
    fd.employee_id
FROM faculty f
LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
WHERE $where_clause
ORDER BY f.last_name ASC, f.first_name ASC
LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get degree options for filter
$degrees_query = "SELECT DISTINCT highest_education FROM faculty_details WHERE highest_education IS NOT NULL AND highest_education != '' ORDER BY highest_education";
$degrees_result = mysqli_query($conn, $degrees_query);
$degrees = [];
while ($row = mysqli_fetch_assoc($degrees_result)) {
    $degrees[] = $row['highest_education'];
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_faculty,
    COALESCE(SUM(CASE WHEN fd.highest_education = 'Bachelor\'s Degree' THEN 1 ELSE 0 END), 0) as bachelors,
    COALESCE(SUM(CASE WHEN fd.highest_education = 'Master\'s Degree' THEN 1 ELSE 0 END), 0) as masters,
    COALESCE(SUM(CASE WHEN fd.highest_education = 'Doctorate' THEN 1 ELSE 0 END), 0) as doctorate,
    COALESCE(SUM(CASE WHEN fd.highest_education IS NULL OR fd.highest_education = '' THEN 1 ELSE 0 END), 0) as no_degree
FROM faculty f
LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
WHERE f.is_active = 1";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manage Employee Degrees</h1>
            <p class="text-gray-600">Update and manage educational qualifications of faculty members</p>
        </div>
        <div class="flex space-x-3">
            <a href="degree-reports.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-chart-bar mr-2"></i>Degree Reports
            </a>
            <a href="manage-faculty.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Faculty
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-users text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Faculty</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_faculty']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-graduation-cap text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Bachelor's</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['bachelors']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-user-graduate text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Master's</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['masters']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-user-tie text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Doctorate</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['doctorate']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-exclamation-triangle text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">No Degree</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['no_degree']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20"
                   placeholder="Name, email, field of study, or school">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Degree Level</label>
            <select name="degree" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20">
                <option value="">All Degrees</option>
                <?php foreach ($degrees as $degree): ?>
                    <option value="<?php echo htmlspecialchars($degree); ?>" <?php echo $degree_filter === $degree ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($degree); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                <i class="fas fa-search mr-2"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Faculty Degrees Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Faculty Educational Qualifications</h3>
        <p class="text-sm text-gray-600">Showing <?php echo $total_records; ?> records</p>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Degree</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Field of Study</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School/University</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Graduated</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                    <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['position']); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($row['department']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($row['highest_education']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php 
                                    switch($row['highest_education']) {
                                        case 'Doctorate': echo 'bg-red-100 text-red-800'; break;
                                        case 'Master\'s Degree': echo 'bg-purple-100 text-purple-800'; break;
                                        case 'Bachelor\'s Degree': echo 'bg-green-100 text-green-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo htmlspecialchars($row['highest_education']); ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    No Degree Set
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($row['field_of_study'] ?? 'Not specified'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($row['school_university'] ?? 'Not specified'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $row['year_graduated'] ? htmlspecialchars($row['year_graduated']) : 'Not specified'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="openDegreeModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>', '<?php echo htmlspecialchars($row['highest_education'] ?? ''); ?>', '<?php echo htmlspecialchars($row['field_of_study'] ?? ''); ?>', '<?php echo htmlspecialchars($row['school_university'] ?? ''); ?>', '<?php echo $row['year_graduated'] ?? ''; ?>')" 
                                    class="text-blue-600 hover:text-blue-900 transition-colors">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="lg:hidden">
        <?php 
        mysqli_data_seek($result, 0); // Reset result pointer
        while ($row = mysqli_fetch_assoc($result)): 
        ?>
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg mr-3">
                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['position']); ?></div>
                        </div>
                    </div>
                    <button onclick="openDegreeModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>', '<?php echo htmlspecialchars($row['highest_education'] ?? ''); ?>', '<?php echo htmlspecialchars($row['field_of_study'] ?? ''); ?>', '<?php echo htmlspecialchars($row['school_university'] ?? ''); ?>', '<?php echo $row['year_graduated'] ?? ''; ?>')" 
                            class="text-blue-600 hover:text-blue-900 transition-colors">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div>
                        <div class="text-xs text-gray-500">Degree</div>
                        <?php if ($row['highest_education']): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                <?php 
                                switch($row['highest_education']) {
                                    case 'Doctorate': echo 'bg-red-100 text-red-800'; break;
                                    case 'Master\'s Degree': echo 'bg-purple-100 text-purple-800'; break;
                                    case 'Bachelor\'s Degree': echo 'bg-green-100 text-green-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo htmlspecialchars($row['highest_education']); ?>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                No Degree Set
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Field of Study</div>
                        <div class="text-sm font-medium"><?php echo htmlspecialchars($row['field_of_study'] ?? 'Not specified'); ?></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">School/University</div>
                        <div class="text-sm font-medium"><?php echo htmlspecialchars($row['school_university'] ?? 'Not specified'); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Year Graduated</div>
                        <div class="text-sm font-medium"><?php echo $row['year_graduated'] ? htmlspecialchars($row['year_graduated']) : 'Not specified'; ?></div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> results
        </div>
        
        <div class="flex space-x-2">
            <?php if ($current_page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" 
                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="px-3 py-2 text-sm font-medium <?php echo $i === $current_page ? 'text-white bg-seait-orange border border-seait-orange' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" 
                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Degree Update Modal -->
<div id="degreeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Update Degree Information</h3>
                <button onclick="closeDegreeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="degreeForm">
                <input type="hidden" id="faculty_id" name="faculty_id">
                <input type="hidden" name="action" value="update_degree">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Employee</label>
                    <div id="employee_name" class="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded"></div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Highest Education <span class="text-red-500">*</span></label>
                    <select id="highest_education" name="highest_education" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20">
                        <option value="">Select Degree</option>
                        <option value="High School">High School</option>
                        <option value="Associate Degree">Associate Degree</option>
                        <option value="Bachelor's Degree">Bachelor's Degree</option>
                        <option value="Master's Degree">Master's Degree</option>
                        <option value="Doctorate">Doctorate</option>
                        <option value="Post-Doctorate">Post-Doctorate</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Field of Study</label>
                    <input type="text" id="field_of_study" name="field_of_study" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20"
                           placeholder="e.g., Computer Science, Business Administration">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">School/University</label>
                    <input type="text" id="school_university" name="school_university" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20"
                           placeholder="e.g., University of the Philippines">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Year Graduated</label>
                    <input type="number" id="year_graduated" name="year_graduated" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20"
                           placeholder="e.g., 2020" min="1950" max="2030">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDegreeModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition-colors">
                        Update Degree
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openDegreeModal(facultyId, employeeName, currentDegree, currentField, currentSchool, currentYear) {
    document.getElementById('faculty_id').value = facultyId;
    document.getElementById('employee_name').textContent = employeeName;
    document.getElementById('highest_education').value = currentDegree;
    document.getElementById('field_of_study').value = currentField;
    document.getElementById('school_university').value = currentSchool;
    document.getElementById('year_graduated').value = currentYear;
    document.getElementById('degreeModal').classList.remove('hidden');
}

function closeDegreeModal() {
    document.getElementById('degreeModal').classList.add('hidden');
    document.getElementById('degreeForm').reset();
}

// Handle form submission
document.getElementById('degreeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('manage-degrees.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeDegreeModal();
            // Reload page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
    });
});

function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => document.body.removeChild(toast));
    
    const toast = document.createElement('div');
    toast.className = `toast-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-xl transform transition-all duration-300 translate-x-full max-w-md`;
    
    // Set background and icon based on type
    let bgColor, icon, iconColor;
    switch(type) {
        case 'success':
            bgColor = 'bg-gradient-to-r from-green-500 to-green-600';
            icon = 'fas fa-check-circle';
            iconColor = 'text-green-100';
            break;
        case 'error':
            bgColor = 'bg-gradient-to-r from-red-500 to-red-600';
            icon = 'fas fa-exclamation-circle';
            iconColor = 'text-red-100';
            break;
        default:
            bgColor = 'bg-gradient-to-r from-blue-500 to-blue-600';
            icon = 'fas fa-info-circle';
            iconColor = 'text-blue-100';
    }
    
    toast.className += ` ${bgColor} text-white`;
    
    toast.innerHTML = `
        <div class="flex items-center space-x-3">
            <i class="${icon} ${iconColor} text-xl"></i>
            <div class="flex-1">
                <p class="font-medium">${message}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 4000);
}
</script>

<?php include 'includes/footer.php'; ?>
