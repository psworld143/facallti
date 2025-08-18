<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Get teacher ID from URL
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

if (!$teacher_id) {
    header('Location: teachers.php');
    exit();
}

// Set page title
$page_title = 'Teacher Subjects';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // Removed: Guidance Office should not be able to assign subjects to teachers
            default:
                $message = "Invalid action.";
                $message_type = "error";
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$semester_filter = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

// Get teacher information
$teacher_query = "SELECT f.*, u.id as user_id, u.email as user_email
                  FROM faculty f
                  LEFT JOIN users u ON f.email = u.email
                  WHERE f.id = ?";
$teacher_stmt = mysqli_prepare($conn, $teacher_query);
mysqli_stmt_bind_param($teacher_stmt, "i", $teacher_id);
mysqli_stmt_execute($teacher_stmt);
$teacher_result = mysqli_stmt_get_result($teacher_stmt);
$teacher = mysqli_fetch_assoc($teacher_result);

// If teacher not found, try alternative query
if (!$teacher) {
    // Try querying just the faculty table
    $teacher_query2 = "SELECT * FROM faculty WHERE id = ?";
    $teacher_stmt2 = mysqli_prepare($conn, $teacher_query2);
    mysqli_stmt_bind_param($teacher_stmt2, "i", $teacher_id);
    mysqli_stmt_execute($teacher_stmt2);
    $teacher_result2 = mysqli_stmt_get_result($teacher_stmt2);
    $teacher = mysqli_fetch_assoc($teacher_result2);
}

if (!$teacher) {
    // Add debugging information
    error_log("Teacher not found for ID: " . $teacher_id);
    header('Location: teachers.php?error=teacher_not_found');
    exit();
}

// Build query for assignments with search and filter
$assignments_where_conditions = [];
$assignments_params = [];
$assignments_param_types = '';

if ($search) {
    $assignments_where_conditions[] = "(s.name LIKE ? OR s.code LIKE ?)";
    $search_param = "%$search%";
    $assignments_params = array_merge($assignments_params, [$search_param, $search_param]);
    $assignments_param_types .= 'ss';
}

if ($semester_filter > 0) {
    $assignments_where_conditions[] = "ts.semester_id = ?";
    $assignments_params[] = $semester_filter;
    $assignments_param_types .= 'i';
}

$assignments_where_clause = '';
if (!empty($assignments_where_conditions)) {
    $assignments_where_clause = 'AND ' . implode(' AND ', $assignments_where_conditions);
}

// Get teacher's subject assignments with search and filter
// Use user_id for teacher_subjects since it references users.id, not faculty.id
$user_id_for_assignments = $teacher['user_id'] ?? null;

if ($user_id_for_assignments) {
    $assignments_query = "SELECT ts.*, s.name as subject_name, s.code as subject_code, s.units,
                                 sem.name as semester_name, sem.academic_year,
                                 (SELECT COUNT(*) FROM student_enrollments se WHERE se.teacher_subject_id = ts.id AND se.status = 'enrolled') as student_count
                          FROM teacher_subjects ts
                          JOIN subjects s ON ts.subject_id = s.id
                          JOIN semesters sem ON ts.semester_id = sem.id
                          WHERE ts.teacher_id = ? $assignments_where_clause
                          ORDER BY sem.academic_year DESC, sem.name ASC, s.name ASC";

    $assignments_stmt = mysqli_prepare($conn, $assignments_query);
    $assignments_params = array_merge([$user_id_for_assignments], $assignments_params);
    $assignments_param_types = 'i' . $assignments_param_types;
    mysqli_stmt_bind_param($assignments_stmt, $assignments_param_types, ...$assignments_params);
    mysqli_stmt_execute($assignments_stmt);
    $assignments_result = mysqli_stmt_get_result($assignments_stmt);

    $assignments = [];
    while ($row = mysqli_fetch_assoc($assignments_result)) {
        $assignments[] = $row;
    }
} else {
    // If no user_id found, set empty assignments array
    $assignments = [];
    // Debug: Log the issue
    error_log("No user_id found for faculty ID: " . $teacher_id . ". Teacher data: " . json_encode($teacher));
}

// Get teacher's classes from Faculty Module
$faculty_classes_query = "SELECT tc.*, cc.subject_title as subject_name, cc.subject_code, cc.units,
                                 (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = tc.id AND ce.status = 'active') as student_count
                          FROM teacher_classes tc
                          JOIN course_curriculum cc ON tc.subject_id = cc.id
                          WHERE tc.teacher_id = ? AND tc.status = 'active'
                          ORDER BY tc.created_at DESC";

$faculty_classes = [];
$faculty_classes_stmt = mysqli_prepare($conn, $faculty_classes_query);
mysqli_stmt_bind_param($faculty_classes_stmt, "i", $teacher_id);
mysqli_stmt_execute($faculty_classes_stmt);
$faculty_classes_result = mysqli_stmt_get_result($faculty_classes_stmt);

while ($row = mysqli_fetch_assoc($faculty_classes_result)) {
    $row['source'] = 'faculty_module';
    $faculty_classes[] = $row;
}

// Get available semesters for filter dropdown
$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
$semesters_result = mysqli_query($conn, $semesters_query);
$semesters = [];
if ($semesters_result) {
    while ($row = mysqli_fetch_assoc($semesters_result)) {
        $semesters[] = $row;
    }
}

// Get available subjects for assignment - Removed: Guidance Office should not be able to add subjects

// Debug: Add some debugging information (add ?debug=1 to URL to see debug info)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
    echo "<h3>Debug Information:</h3>";
    echo "<p><strong>Faculty ID:</strong> " . $teacher_id . "</p>";
    echo "<p><strong>User ID:</strong> " . ($teacher['user_id'] ?? 'NULL') . "</p>";
    echo "<p><strong>Teacher Email:</strong> " . ($teacher['email'] ?? 'NULL') . "</p>";
    echo "<p><strong>IntelliEVal Assignments Count:</strong> " . count($assignments) . "</p>";
    echo "<p><strong>Faculty Module Classes Count:</strong> " . count($faculty_classes) . "</p>";
    
    // Show sample assignment data
    if (!empty($assignments)) {
        echo "<p><strong>Sample Assignment:</strong></p>";
        echo "<pre>" . print_r($assignments[0], true) . "</pre>";
    }
    
    // Show sample faculty class data
    if (!empty($faculty_classes)) {
        echo "<p><strong>Sample Faculty Class:</strong></p>";
        echo "<pre>" . print_r($faculty_classes[0], true) . "</pre>";
    }
    echo "</div>";
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Teacher Subject Management</h1>
            <p class="text-sm sm:text-base text-gray-600">Manage subject assignments for teachers</p>
        </div>
        <a href="teachers.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Teachers
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Teacher Information -->
<div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
    <div class="flex items-center">
        <div class="h-16 w-16 rounded-full bg-seait-orange flex items-center justify-center mr-4">
            <span class="text-white text-xl font-medium"><?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?></span>
        </div>
        <div>
            <h2 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h2>
            <p class="text-gray-600"><?php echo htmlspecialchars($teacher['department'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($teacher['position'] ?? 'N/A'); ?></p>
            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['email']); ?> • <?php echo htmlspecialchars($teacher['phone'] ?? 'N/A'); ?></p>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>"
                   placeholder="Search by subject name or code..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
            <select name="semester" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                <option value="">All Semesters</option>
                <?php foreach ($semesters as $semester): ?>
                    <option value="<?php echo $semester['id']; ?>" <?php echo (isset($_GET['semester']) && $_GET['semester'] == $semester['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($semester['name'] . ' (' . $semester['academic_year'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end space-x-2">
            <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition text-sm">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <a href="teacher-subjects.php?teacher_id=<?php echo $teacher_id; ?>" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </form>
</div>

<!-- Add Subject Assignment Button -->
<!-- Removed: Guidance Office should not be able to add subjects for teachers -->

<!-- Subject Assignments -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Subject Assignments (<?php echo count($assignments) + count($faculty_classes); ?>)</h2>
        <p class="text-sm text-gray-500 mt-1">
            Showing both IntelliEVal assignments and Faculty Module classes
        </p>
    </div>

    <!-- Desktop Table -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester/Class</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($assignments) && empty($faculty_classes)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                        No subject assignments found. <?php if (isset($_GET['search']) || isset($_GET['semester'])): ?>Try adjusting your search criteria.<?php else: ?>Assign subjects to this teacher to get started.<?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): ?>
                    <tr class="bg-blue-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($assignment['subject_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($assignment['subject_code']); ?> • <?php echo htmlspecialchars($assignment['units']); ?> units</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($assignment['semester_name']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($assignment['academic_year']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($assignment['section'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($assignment['student_count']); ?> students enrolled
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $assignment['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo ucfirst($assignment['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                IntelliEVal
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editAssignment(<?php echo $assignment['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteAssignment(<?php echo $assignment['id']; ?>)" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php foreach ($faculty_classes as $class): ?>
                    <tr class="bg-green-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($class['subject_code']); ?> • <?php echo htmlspecialchars($class['units']); ?> units</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Faculty Class</div>
                            <div class="text-sm text-gray-500">Join Code: <?php echo htmlspecialchars($class['join_code']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($class['section']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($class['student_count']); ?> students enrolled
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $class['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo ucfirst($class['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                Faculty Module
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <!-- Removed: Guidance Office should not access Faculty Module features -->
                            <span class="text-gray-400 text-sm">View Only</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="lg:hidden">
        <?php if (empty($assignments) && empty($faculty_classes)): ?>
            <div class="p-4 text-center text-gray-500">
                No subject assignments found. <?php if (isset($_GET['search']) || isset($_GET['semester'])): ?>Try adjusting your search criteria.<?php else: ?>Assign subjects to this teacher to get started.<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="p-4 space-y-4">
                <?php foreach ($assignments as $assignment): ?>
                    <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-400">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($assignment['subject_name']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($assignment['subject_code']); ?> • <?php echo htmlspecialchars($assignment['units']); ?> units</p>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="px-2 py-1 text-xs rounded-full <?php
                                    echo $assignment['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                ?>">
                                    <?php echo ucfirst($assignment['status']); ?>
                                </span>
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 mt-1">
                                    IntelliEVal
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                            <div>
                                <p class="text-gray-500">Semester</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($assignment['semester_name']); ?></p>
                                <p class="text-gray-500 text-xs"><?php echo htmlspecialchars($assignment['academic_year']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Section</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($assignment['section'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-gray-500">Students</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($assignment['student_count']); ?> students enrolled</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex space-x-2">
                                <button onclick="editAssignment(<?php echo $assignment['id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteAssignment(<?php echo $assignment['id']; ?>)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($faculty_classes as $class): ?>
                    <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-400">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($class['subject_name']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($class['subject_code']); ?> • <?php echo htmlspecialchars($class['units']); ?> units</p>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="px-2 py-1 text-xs rounded-full <?php
                                    echo $class['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                ?>">
                                    <?php echo ucfirst($class['status']); ?>
                                </span>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 mt-1">
                                    Faculty Module
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                            <div>
                                <p class="text-gray-500">Class Type</p>
                                <p class="text-gray-900">Faculty Class</p>
                                <p class="text-gray-500 text-xs">Join Code: <?php echo htmlspecialchars($class['join_code']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Section</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($class['section']); ?></p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-gray-500">Students</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($class['student_count']); ?> students enrolled</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex space-x-2">
                                <!-- Removed: Guidance Office should not access Faculty Module features -->
                                <span class="text-gray-400 text-sm">View Only</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function editAssignment(assignmentId) {
    // Redirect to edit page
    window.location.href = 'edit-teacher-assignment.php?id=' + assignmentId;
}

function deleteAssignment(assignmentId) {
    if (confirm('Are you sure you want to remove this subject assignment? This action cannot be undone.')) {
        // Create a form and submit it to delete the assignment
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete-teacher-assignment.php';
        form.innerHTML = `
            <input type="hidden" name="assignment_id" value="${assignmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>