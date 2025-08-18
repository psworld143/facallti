<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';
require_once 'includes/student-functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10; // Allow user to select page size
$per_page = in_array($per_page, [5, 10, 25, 50]) ? $per_page : 10; // Validate page size
$offset = ($page - 1) * $per_page;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register_student':
                $response = registerStudent($conn, $_POST);
                break;
            case 'import_students':
                $response = importStudentsFromExcel($conn, $_FILES);
                break;
            case 'edit_student':
                $student_id = (int)$_POST['student_id'];
                $response = updateStudent($conn, $student_id, $_POST);
                break;
            case 'delete_student':
                $student_id = (int)$_POST['student_id'];
                $response = deleteStudent($conn, $student_id);
                break;
            case 'get_student':
                $student_id = (int)$_POST['student_id'];
                $student = getStudentById($conn, $student_id);
                if ($student) {
                    $response = ['success' => true, 'student' => $student];
                } else {
                    $response = ['success' => false, 'message' => 'Student not found.'];
                }
                break;
            default:
                $response = ['success' => false, 'message' => 'Invalid action'];
        }

        // If it's an AJAX request, return JSON response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }
}

// Build the students query with filters
$students_query = "SELECT s.*, c.name as course_name, c.short_name as course_short_name, sai.year_level, sai.section
                   FROM students s
                   LEFT JOIN student_academic_info sai ON s.id = sai.student_id
                   LEFT JOIN courses c ON sai.program_id = c.id
                   WHERE s.status != 'deleted'";

$params = [];
$param_types = "";

if ($course_filter) {
    $students_query .= " AND sai.program_id = ?";
    $params[] = $course_filter;
    $param_types .= "i";
}

if ($status_filter) {
    $students_query .= " AND s.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$students_query .= " ORDER BY s.created_at DESC";

// Get total count for pagination
$count_query = str_replace("SELECT s.*, c.name as course_name, c.short_name as course_short_name, sai.year_level, sai.section", "SELECT COUNT(*) as total", $students_query);
$count_query = preg_replace('/ORDER BY.*$/', '', $count_query);

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
}

$total_students = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_students / $per_page);

// Add pagination to the main query
$students_query .= " LIMIT $per_page OFFSET $offset";

// Execute the query with parameters
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $students_query);
    if (!$stmt) {
        die("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $students_result = mysqli_stmt_get_result($stmt);
} else {
    $students_result = mysqli_query($conn, $students_query);
    if (!$students_result) {
        die("Query failed: " . mysqli_error($conn));
    }
}

// Get courses for filter dropdown
$courses_query = "SELECT id, name, short_name FROM courses WHERE is_active = 1 ORDER BY name";
$courses_result = mysqli_query($conn, $courses_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Admin Dashboard</title>
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

        /* Table responsive styles */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 0.5rem;
            max-width: 100%;
        }

        .table-container table {
            width: 100%;
            table-layout: auto;
        }

        /* Responsive table design */
        @media (max-width: 1200px) {
            .table-container table th,
            .table-container table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 1024px) {
            .table-container table th:nth-child(3),
            .table-container table td:nth-child(3) {
                min-width: 180px; /* Email column */
            }

            .table-container table th:nth-child(4),
            .table-container table td:nth-child(4) {
                min-width: 140px; /* Course column */
            }
        }

        @media (max-width: 768px) {
            .table-container table th:nth-child(2),
            .table-container table td:nth-child(2) {
                min-width: 100px; /* Name column */
            }

            .table-container table th:nth-child(7),
            .table-container table td:nth-child(7) {
                min-width: 80px; /* Registered date */
            }

            .table-container table th,
            .table-container table td {
                padding: 0.375rem 0.125rem;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 640px) {
            .table-container {
                font-size: 0.75rem;
            }

            .table-container table th,
            .table-container table td {
                padding: 0.25rem 0.125rem;
            }
        }

        /* Ensure text truncation works properly */
        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Responsive pagination */
        @media (max-width: 768px) {
            .pagination-info {
                text-align: center;
                margin-bottom: 1rem;
            }

            .pagination-controls {
                justify-content: center;
                flex-wrap: wrap;
            }
        }

        /* Mobile-friendly table layout */
        @media (max-width: 480px) {
            .table-container {
                overflow-x: visible;
            }

            .table-container table {
                display: block;
            }

            .table-container thead {
                display: none;
            }

            .table-container tbody {
                display: block;
            }

            .table-container tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                padding: 1rem;
                background: white;
            }

            .table-container td {
                display: block;
                text-align: left;
                padding: 0.5rem 0;
                border: none;
                position: relative;
                padding-left: 8rem;
            }

            .table-container td:before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 7rem;
                font-weight: 600;
                color: #6b7280;
                font-size: 0.75rem;
                text-transform: uppercase;
            }

            .table-container td:first-child {
                padding-top: 0;
            }

            .table-container td:last-child {
                padding-bottom: 0;
            }
        }

        /* Modal animations */
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
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-seait-dark mb-2">Student Registration</h1>
                        <p class="text-gray-600">Manage student registrations and imports</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="openManualRegistration()" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-user-plus mr-2"></i>Add Student
                        </button>
                        <button onclick="openExcelImport()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition">
                            <i class="fas fa-file-excel mr-2"></i>Import Excel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($response)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $response['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <div class="flex items-center">
                    <i class="fas <?php echo $response['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($response['message']); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <?php
                $total_students = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM students WHERE status != 'deleted'"))[0];
                $active_students = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM students WHERE status = 'active'"))[0];
                $pending_students = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM students WHERE status = 'pending'"))[0];
                $today_registrations = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM students WHERE DATE(created_at) = CURDATE()"))[0];
                ?>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Students</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_students; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-user-check text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Students</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $active_students; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $pending_students; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-seait-orange bg-opacity-20 text-seait-orange">
                            <i class="fas fa-calendar-day text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Today's Registrations</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $today_registrations; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="course" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                        <select id="course" name="course" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                            <option value="">All Courses</option>
                            <?php while ($course = mysqli_fetch_assoc($courses_result)): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['short_name']); ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="status" name="status" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label for="per_page" class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
                        <select id="per_page" name="per_page" onchange="this.form.submit()" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                            <option value="5" <?php echo $per_page == 5 ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                        <a href="students.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">All Students</h3>
                    <p class="text-sm text-gray-500 mt-1">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_students); ?> of <?php echo $total_students; ?> students</p>
                </div>
                <div class="table-container">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            while ($student = mysqli_fetch_assoc($students_result)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900" data-label="Student ID">
                                    <?php echo htmlspecialchars($student['student_id']); ?>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900" data-label="Name">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900" data-label="Email">
                                    <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($student['email']); ?>">
                                        <?php echo htmlspecialchars($student['email']); ?>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900" data-label="Course">
                                    <?php if ($student['course_name']): ?>
                                        <div class="max-w-xs">
                                            <span class="font-medium"><?php echo htmlspecialchars($student['course_short_name']); ?></span>
                                            <div class="text-xs text-gray-500 truncate" title="<?php echo htmlspecialchars($student['course_name']); ?>">
                                                <?php echo htmlspecialchars($student['course_name']); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900" data-label="Year Level">
                                    <?php echo $student['year_level'] ? htmlspecialchars($student['year_level']) : '-'; ?>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap" data-label="Status">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php echo $student['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Registered">
                                    <?php echo date('M d, Y', strtotime($student['created_at'])); ?>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm font-medium" data-label="Actions">
                                    <div class="flex space-x-1">
                                        <button onclick="editStudent(<?php echo $student['id']; ?>)" class="text-blue-600 hover:text-blue-800 p-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteStudent(<?php echo $student['id']; ?>)" class="text-red-600 hover:text-red-800 p-1" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                        <div class="text-sm text-gray-700 pagination-info">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_students); ?> of <?php echo $total_students; ?> results
                        </div>
                        <div class="flex items-center space-x-2 pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    1
                                </a>
                                <?php if ($start_page > 2): ?>
                                    <span class="px-3 py-2 text-sm text-gray-500">...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                   class="px-3 py-2 text-sm font-medium <?php echo $i == $page ? 'text-white bg-seait-orange border border-seait-orange' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="px-3 py-2 text-sm text-gray-500">...</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    <?php echo $total_pages; ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Manual Registration Modal -->
    <div id="manualRegistrationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">Register New Student</h3>
                        <button onclick="closeManualRegistration()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form id="studentRegistrationForm" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="register_student">

                        <div>
                            <label for="student_id" class="block text-sm font-medium text-gray-700">Student ID *</label>
                            <input type="text" id="student_id" name="student_id" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                            <input type="email" id="email" name="email" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeManualRegistration()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-seait-orange to-orange-600 text-white rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-user-plus mr-2"></i>Register Student
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Excel Import Modal -->
    <div id="excelImportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">Import Students from Excel</h3>
                        <button onclick="closeExcelImport()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form id="excelImportForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="import_students">

                        <div>
                            <label for="excel_file" class="block text-sm font-medium text-gray-700">Excel File *</label>
                            <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                            <p class="mt-1 text-sm text-gray-500">Supported formats: .xlsx, .xls</p>
                        </div>

                        <div class="bg-blue-50 p-4 rounded-md">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">Excel Format Requirements:</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• Column A: Student ID</li>
                                <li>• Column B: First Name</li>
                                <li>• Column C: Middle Name (optional)</li>
                                <li>• Column D: Last Name</li>
                                <li>• Column E: Email</li>
                            </ul>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeExcelImport()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-file-excel mr-2"></i>Import Students
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editStudentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">Edit Student</h3>
                        <button onclick="closeEditStudent()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form id="editStudentForm" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="edit_student">
                        <input type="hidden" id="edit_student_id" name="student_id">

                        <div>
                            <label for="edit_student_number" class="block text-sm font-medium text-gray-700">Student ID *</label>
                            <input type="text" id="edit_student_number" name="student_number" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="edit_first_name" class="block text-sm font-medium text-gray-700">First Name *</label>
                            <input type="text" id="edit_first_name" name="first_name" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="edit_middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" id="edit_middle_name" name="middle_name"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="edit_last_name" class="block text-sm font-medium text-gray-700">Last Name *</label>
                            <input type="text" id="edit_last_name" name="last_name" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="edit_email" class="block text-sm font-medium text-gray-700">Email *</label>
                            <input type="email" id="edit_email" name="email" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                        </div>

                        <div>
                            <label for="edit_status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="edit_status" name="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-seait-orange focus:border-seait-orange">
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeEditStudent()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-seait-orange to-orange-600 text-white rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-save mr-2"></i>Update Student
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteStudentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Student</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this student? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Student will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible to users
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex justify-center space-x-3">
                        <button onclick="closeDeleteStudent()"
                                class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button onclick="confirmDeleteStudent()"
                                class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                            <i class="fas fa-trash mr-2"></i>Delete Permanently
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-seait-orange"></div>
                <span class="text-gray-700">Processing...</span>
            </div>
        </div>
    </div>

    <script src="assets/js/student-registration.js"></script>
</body>
</html>