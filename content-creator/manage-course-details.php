<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a content creator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_requirement':
                $course_id = (int)$_POST['course_id'];
                $requirement_type = $_POST['requirement_type'];
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);

                if (empty($title) || empty($course_id)) {
                    $error = 'Course and requirement title are required.';
                } else {
                    // Get the next sort order for this course and requirement type
                    $sort_query = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_sort FROM course_requirements WHERE course_id = ? AND requirement_type = ?";
                    $sort_stmt = mysqli_prepare($conn, $sort_query);
                    mysqli_stmt_bind_param($sort_stmt, 'is', $course_id, $requirement_type);
                    mysqli_stmt_execute($sort_stmt);
                    $sort_result = mysqli_stmt_get_result($sort_stmt);
                    $sort_order = mysqli_fetch_assoc($sort_result)['next_sort'];
                    mysqli_stmt_close($sort_stmt);

                    $query = "INSERT INTO course_requirements (course_id, requirement_type, title, description, sort_order, created_by)
                              VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'isssii', $course_id, $requirement_type, $title, $description, $sort_order, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Requirement added successfully!';
                    } else {
                        $error = 'Error adding requirement: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'edit_requirement':
                $requirement_id = (int)$_POST['requirement_id'];
                $course_id = (int)$_POST['course_id'];
                $requirement_type = $_POST['requirement_type'];
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);

                if (empty($title) || empty($course_id)) {
                    $error = 'Course and requirement title are required.';
                } else {
                    // Keep the existing sort order when editing
                    $query = "UPDATE course_requirements SET course_id = ?, requirement_type = ?, title = ?, description = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'isssi', $course_id, $requirement_type, $title, $description, $requirement_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Requirement updated successfully!';
                    } else {
                        $error = 'Error updating requirement: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'delete_requirement':
                $requirement_id = (int)$_POST['requirement_id'];

                $query = "DELETE FROM course_requirements WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'i', $requirement_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Requirement deleted successfully!';
                } else {
                    $error = 'Error deleting requirement: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'edit_curriculum':
                $curriculum_id = (int)$_POST['curriculum_id'];
                $course_id = (int)$_POST['course_id'];
                $year_level = $_POST['year_level'];
                $semester = $_POST['semester'];
                $subject_code = trim($_POST['subject_code']);
                $subject_title = trim($_POST['subject_title']);
                $units = (float)$_POST['units'];
                $lecture_hours = (int)$_POST['lecture_hours'];
                $laboratory_hours = (int)$_POST['laboratory_hours'];
                $description = trim($_POST['curriculum_description']);
                $prerequisite_id = !empty($_POST['prerequisite_id']) ? (int)$_POST['prerequisite_id'] : null;

                if (empty($subject_code) || empty($subject_title) || empty($course_id)) {
                    $error = 'Course, subject code, and subject title are required.';
                } else {
                    // Keep the existing sort order when editing
                    $query = "UPDATE course_curriculum SET course_id = ?, year_level = ?, semester = ?, subject_code = ?, subject_title = ?, units = ?, lecture_hours = ?, laboratory_hours = ?, description = ?, prerequisite_id = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'issssdiiisii', $course_id, $year_level, $semester, $subject_code, $subject_title, $units, $lecture_hours, $laboratory_hours, $description, $prerequisite_id, $curriculum_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Curriculum subject updated successfully!';
                    } else {
                        $error = 'Error updating curriculum: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'delete_curriculum':
                $curriculum_id = (int)$_POST['curriculum_id'];

                $query = "DELETE FROM course_curriculum WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'i', $curriculum_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Curriculum subject deleted successfully!';
                } else {
                    $error = 'Error deleting curriculum: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'add_curriculum':
                $course_id = (int)$_POST['course_id'];
                $year_level = $_POST['year_level'];
                $semester = $_POST['semester'];
                $subject_code = trim($_POST['subject_code']);
                $subject_title = trim($_POST['subject_title']);
                $units = (float)$_POST['units'];
                $lecture_hours = (int)$_POST['lecture_hours'];
                $laboratory_hours = (int)$_POST['laboratory_hours'];
                $description = trim($_POST['curriculum_description']);
                $prerequisite_id = !empty($_POST['prerequisite_id']) ? (int)$_POST['prerequisite_id'] : null;

                if (empty($subject_code) || empty($subject_title) || empty($course_id)) {
                    $error = 'Course, subject code, and subject title are required.';
                } else {
                    // Get the next sort order for this course, year level, and semester
                    $sort_query = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_sort FROM course_curriculum WHERE course_id = ? AND year_level = ? AND semester = ?";
                    $sort_stmt = mysqli_prepare($conn, $sort_query);
                    mysqli_stmt_bind_param($sort_stmt, 'iss', $course_id, $year_level, $semester);
                    mysqli_stmt_execute($sort_stmt);
                    $sort_result = mysqli_stmt_get_result($sort_stmt);
                    $sort_order = mysqli_fetch_assoc($sort_result)['next_sort'];
                    mysqli_stmt_close($sort_stmt);

                    $query = "INSERT INTO course_curriculum (course_id, year_level, semester, subject_code, subject_title, units, lecture_hours, laboratory_hours, description, sort_order, prerequisite_id, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'issssdiiisii', $course_id, $year_level, $semester, $subject_code, $subject_title, $units, $lecture_hours, $laboratory_hours, $description, $sort_order, $prerequisite_id, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Curriculum subject added successfully!';
                    } else {
                        $error = 'Error adding curriculum: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
        }
    }
}

// Fetch courses
$courses_query = "SELECT c.*, co.name as college_name
                  FROM courses c
                  JOIN colleges co ON c.college_id = co.id
                  WHERE c.is_active = 1
                  ORDER BY co.sort_order ASC, c.sort_order ASC, c.name ASC";
$courses_result = mysqli_query($conn, $courses_query);

// Fetch requirements for display
$requirements_query = "SELECT cr.*, c.name as course_name
                      FROM course_requirements cr
                      JOIN courses c ON cr.course_id = c.id
                      ORDER BY c.name ASC, cr.requirement_type ASC, cr.sort_order ASC";
$requirements_result = mysqli_query($conn, $requirements_query);

// Fetch curriculum for display
$curriculum_query = "SELECT cc.*, c.name as course_name,
                     prereq.subject_code as prerequisite_code, prereq.subject_title as prerequisite_title
                     FROM course_curriculum cc
                     JOIN courses c ON cc.course_id = c.id
                     LEFT JOIN course_curriculum prereq ON cc.prerequisite_id = prereq.id
                     ORDER BY c.name ASC, cc.year_level ASC, cc.semester ASC, cc.sort_order ASC";
$curriculum_result = mysqli_query($conn, $curriculum_query);

// Pagination settings
$items_per_page = 10;
$requirements_page = isset($_GET['requirements_page']) ? (int)$_GET['requirements_page'] : 1;
$curriculum_page = isset($_GET['curriculum_page']) ? (int)$_GET['curriculum_page'] : 1;

// Get total counts
$requirements_count_query = "SELECT COUNT(*) as total FROM course_requirements";
$requirements_count_result = mysqli_query($conn, $requirements_count_query);
$requirements_total = mysqli_fetch_assoc($requirements_count_result)['total'];
$requirements_total_pages = ceil($requirements_total / $items_per_page);

$curriculum_count_query = "SELECT COUNT(*) as total FROM course_curriculum";
$curriculum_count_result = mysqli_query($conn, $curriculum_count_query);
$curriculum_total = mysqli_fetch_assoc($curriculum_count_result)['total'];
$curriculum_total_pages = ceil($curriculum_total / $items_per_page);

// Fetch paginated requirements
$requirements_offset = ($requirements_page - 1) * $items_per_page;
$requirements_query = "SELECT cr.*, c.name as course_name
                      FROM course_requirements cr
                      JOIN courses c ON cr.course_id = c.id
                      ORDER BY c.name ASC, cr.requirement_type ASC, cr.sort_order ASC
                      LIMIT $items_per_page OFFSET $requirements_offset";
$requirements_result = mysqli_query($conn, $requirements_query);

// Fetch paginated curriculum
$curriculum_offset = ($curriculum_page - 1) * $items_per_page;
$curriculum_query = "SELECT cc.*, c.name as course_name,
                     prereq.subject_code as prerequisite_code, prereq.subject_title as prerequisite_title
                     FROM course_curriculum cc
                     JOIN courses c ON cc.course_id = c.id
                     LEFT JOIN course_curriculum prereq ON cc.prerequisite_id = prereq.id
                     ORDER BY c.name ASC, cc.year_level ASC, cc.semester ASC, cc.sort_order ASC
                     LIMIT $items_per_page OFFSET $curriculum_offset";
$curriculum_result = mysqli_query($conn, $curriculum_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Course Details - SEAIT Content Creator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

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

        /* Tab Styles */
        .tab-button {
            transition: all 0.3s ease;
        }

        .tab-button.active {
            border-color: #FF6B35;
            color: #FF6B35;
        }

        .tab-button:not(.active) {
            border-color: transparent;
            color: #6B7280;
        }

        .tab-button:hover:not(.active) {
            color: #FF6B35;
            border-color: #FF6B35;
        }

        .tab-content {
            transition: opacity 0.3s ease;
        }

        /* Responsive Table Styles */
        .responsive-table {
            width: 100%;
            border-collapse: collapse;
        }

        .responsive-table th,
        .responsive-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            word-wrap: break-word;
            max-width: 200px;
        }

        .responsive-table th {
            background-color: #f9fafb;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }

        .responsive-table td {
            font-size: 0.875rem;
            color: #374151;
        }

        /* Mobile-first responsive design */
        @media (max-width: 768px) {
            .responsive-table {
                display: block;
                width: 100%;
            }

            .responsive-table thead {
                display: none;
            }

            .responsive-table tbody {
                display: block;
                width: 100%;
            }

            .responsive-table tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                background-color: white;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            }

            .responsive-table td {
                display: block;
                text-align: left;
                padding: 0.75rem;
                border: none;
                border-bottom: 1px solid #f3f4f6;
                position: relative;
                padding-left: 50%;
                max-width: none;
            }

            .responsive-table td:last-child {
                border-bottom: none;
            }

            .responsive-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 0.75rem;
                width: 45%;
                font-weight: 600;
                font-size: 0.75rem;
                text-transform: uppercase;
                color: #6b7280;
            }

            .responsive-table .actions-cell {
                padding-left: 0.75rem;
            }

            .responsive-table .actions-cell:before {
                display: none;
            }

            /* Action buttons in row for mobile */
            .responsive-table .actions-cell .flex {
                flex-direction: row;
                gap: 0.5rem;
            }

            .responsive-table .actions-cell button {
                flex: 1;
                padding: 0.5rem;
                font-size: 0.75rem;
                white-space: nowrap;
            }
        }

        /* Tablet responsive adjustments */
        @media (min-width: 769px) and (max-width: 1024px) {
            .responsive-table th,
            .responsive-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .responsive-table .hidden-md {
                display: table-cell;
            }
        }

        /* Ensure table container doesn't overflow */
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        @media (min-width: 769px) {
            .table-container {
                overflow-x: visible;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white fixed top-0 left-0 right-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-10 w-auto">
                    <div>
                        <h1 class="text-xl font-bold text-seait-dark">SEAIT Content Creator</h1>
                        <p class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-seait-dark hover:text-seait-orange transition">
                        <i class="fas fa-home mr-2"></i><span class="hidden sm:inline">View Site</span>
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i><span class="hidden sm:inline">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen pt-16">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-72 overflow-y-auto h-screen">
            <div class="p-4 lg:p-8">
                <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Main Page Header -->
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Manage Course Details</h1>
                    <p class="text-gray-600">Add and manage course requirements and curriculum subjects</p>
                </div>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Course Details Management</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Course Requirements:</strong> Add admission, graduation, or prerequisite requirements for each course. Sort order is automatically assigned.</p>
                                <p><strong>Curriculum Subjects:</strong> Add subjects with their details including units, lecture hours, and laboratory hours. Sort order is automatically assigned within each year level and semester.</p>
                                <p><strong>Organization:</strong> Use the tabs below to switch between managing requirements and curriculum subjects.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button onclick="switchTab('requirements')" id="requirements-tab" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm active">
                                <i class="fas fa-list-check mr-2"></i>
                                Manage Requirements
                            </button>
                            <button onclick="switchTab('curriculum')" id="curriculum-tab" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                <i class="fas fa-graduation-cap mr-2"></i>
                                Manage Curriculum
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Requirements Tab Content -->
                <div id="requirements-content" class="tab-content">
                    <!-- Add Course Requirement Form -->
                    <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-6 lg:mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-blue-100 rounded-full mr-3">
                                    <i class="fas fa-list-check text-blue-600"></i>
                                </div>
                                <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add Course Requirement</h2>
                            </div>
                            <button type="button" onclick="toggleForm('requirement-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                                <i class="fas fa-chevron-down" id="requirement-toggle-icon"></i>
                            </button>
                        </div>
                        <form method="POST" id="requirement-form" class="space-y-4">
                            <input type="hidden" name="action" value="add_requirement">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Course <span class="text-red-500">*</span></label>
                                <select name="course_id" id="add_course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                    <option value="">Select Course</option>
                                    <?php mysqli_data_seek($courses_result, 0); ?>
                                    <?php while($course = mysqli_fetch_assoc($courses_result)): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['college_name']); ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Requirement Type <span class="text-red-500">*</span></label>
                                    <select name="requirement_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                        <option value="">Select Type</option>
                                        <option value="admission">Admission</option>
                                        <option value="graduation">Graduation</option>
                                        <option value="prerequisite">Prerequisite</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" required placeholder="Enter requirement title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" rows="3" placeholder="Enter requirement description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                                    <i class="fas fa-plus mr-2"></i>Add Requirement
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Requirements List -->
                    <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Manage Requirements</h2>
                            <input type="text" id="requirementsSearch" placeholder="Search requirements..." class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seait-orange" style="max-width: 250px;">
                        </div>
                        <div class="table-container">
                            <table class="responsive-table" id="requirementsTable">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Type</th>
                                        <th>Title</th>
                                        <th class="hidden md:table-cell">Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php mysqli_data_seek($requirements_result, 0); ?>
                                    <?php while($requirement = mysqli_fetch_assoc($requirements_result)): ?>
                                    <tr>
                                        <td data-label="Course"><?php echo htmlspecialchars($requirement['course_name']); ?></td>
                                        <td data-label="Type"><?php echo ucfirst(htmlspecialchars($requirement['requirement_type'])); ?></td>
                                        <td data-label="Title"><?php echo htmlspecialchars($requirement['title']); ?></td>
                                        <td data-label="Description" class="hidden md:table-cell"><?php echo htmlspecialchars($requirement['description']); ?></td>
                                        <td data-label="Actions" class="actions-cell">
                                            <div class="flex space-x-2">
                                                <button onclick="editRequirement(<?php echo $requirement['id']; ?>)" class="text-seait-orange hover:text-orange-600 transition text-sm">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </button>
                                                <button onclick="deleteRequirement(<?php echo $requirement['id']; ?>, '<?php echo addslashes($requirement['title']); ?>')" class="text-red-500 hover:text-red-700 transition text-sm">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Requirements Pagination -->
                        <?php if ($requirements_total_pages > 1): ?>
                        <div class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo (($requirements_page - 1) * $items_per_page) + 1; ?> to <?php echo min($requirements_page * $items_per_page, $requirements_total); ?> of <?php echo $requirements_total; ?> requirements
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($requirements_page > 1): ?>
                                <a href="?requirements_page=<?php echo $requirements_page - 1; ?>&curriculum_page=<?php echo $curriculum_page; ?>" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Previous</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $requirements_page - 2); $i <= min($requirements_total_pages, $requirements_page + 2); $i++): ?>
                                <a href="?requirements_page=<?php echo $i; ?>&curriculum_page=<?php echo $curriculum_page; ?>" class="px-3 py-2 text-sm rounded transition <?php echo $i == $requirements_page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>

                                <?php if ($requirements_page < $requirements_total_pages): ?>
                                <a href="?requirements_page=<?php echo $requirements_page + 1; ?>&curriculum_page=<?php echo $curriculum_page; ?>" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Curriculum Tab Content -->
                <div id="curriculum-content" class="tab-content hidden">
                    <!-- Add Curriculum Form -->
                    <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-6 lg:mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-green-100 rounded-full mr-3">
                                    <i class="fas fa-graduation-cap text-green-600"></i>
                                </div>
                                <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add Curriculum Subject</h2>
                            </div>
                            <button type="button" onclick="toggleForm('curriculum-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                                <i class="fas fa-chevron-down" id="curriculum-toggle-icon"></i>
                            </button>
                        </div>
                        <form method="POST" id="curriculum-form" class="space-y-4">
                            <input type="hidden" name="action" value="add_curriculum">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Course <span class="text-red-500">*</span></label>
                                <select name="course_id" id="curriculum_course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                    <option value="">Select Course</option>
                                    <?php mysqli_data_seek($courses_result, 0); ?>
                                    <?php while($course = mysqli_fetch_assoc($courses_result)): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['college_name']); ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Year Level <span class="text-red-500">*</span></label>
                                    <select name="year_level" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                        <option value="">Select Year</option>
                                        <option value="first_year">First Year</option>
                                        <option value="second_year">Second Year</option>
                                        <option value="third_year">Third Year</option>
                                        <option value="fourth_year">Fourth Year</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester <span class="text-red-500">*</span></label>
                                    <select name="semester" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                        <option value="">Select Semester</option>
                                        <option value="first_semester">First Semester</option>
                                        <option value="second_semester">Second Semester</option>
                                        <option value="summer">Summer</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Code <span class="text-red-500">*</span></label>
                                    <input type="text" name="subject_code" required placeholder="e.g., MATH101" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Title <span class="text-red-500">*</span></label>
                                    <input type="text" name="subject_title" required placeholder="Enter subject title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prerequisite Subject</label>
                                <select name="prerequisite_id" id="prerequisite_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                    <option value="">Select Prerequisite Subject (Optional)</option>
                                    <!-- Prerequisite options will be populated here -->
                                </select>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Units</label>
                                    <input type="number" name="units" step="0.1" value="3.0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lecture Hours</label>
                                    <input type="number" name="lecture_hours" value="3" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Laboratory Hours</label>
                                    <input type="number" name="laboratory_hours" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="curriculum_description" rows="3" placeholder="Enter subject description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                                    <i class="fas fa-plus mr-2"></i>Add Curriculum Subject
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Curriculum List -->
                    <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Manage Curriculum</h2>
                            <input type="text" id="curriculumSearch" placeholder="Search curriculum..." class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seait-orange" style="max-width: 250px;">
                        </div>
                        <div class="table-container">
                            <table class="responsive-table" id="curriculumTable">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Year</th>
                                        <th>Semester</th>
                                        <th>Subject Code</th>
                                        <th class="hidden lg:table-cell">Subject Title</th>
                                        <th class="hidden md:table-cell">Prerequisite</th>
                                        <th>Units</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php mysqli_data_seek($curriculum_result, 0); ?>
                                    <?php while($subject = mysqli_fetch_assoc($curriculum_result)): ?>
                                    <tr>
                                        <td data-label="Course"><?php echo htmlspecialchars($subject['course_name']); ?></td>
                                        <td data-label="Year"><?php echo ucfirst(str_replace('_', ' ', $subject['year_level'])); ?></td>
                                        <td data-label="Semester"><?php echo ucfirst(str_replace('_', ' ', $subject['semester'])); ?></td>
                                        <td data-label="Subject Code"><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                        <td data-label="Subject Title" class="hidden lg:table-cell"><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                                        <td data-label="Prerequisite" class="hidden md:table-cell">
                                            <?php if ($subject['prerequisite_code'] && $subject['prerequisite_title']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo htmlspecialchars($subject['prerequisite_code']); ?>
                                                </span>
                                                <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($subject['prerequisite_title']); ?></div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Units"><?php echo htmlspecialchars($subject['units']); ?></td>
                                        <td data-label="Actions" class="actions-cell">
                                            <div class="flex space-x-2">
                                                <button onclick="editCurriculum(<?php echo $subject['id']; ?>)" class="text-seait-orange hover:text-orange-600 transition text-sm">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </button>
                                                <button onclick="deleteCurriculum(<?php echo $subject['id']; ?>, '<?php echo addslashes($subject['subject_code']); ?>', '<?php echo addslashes($subject['subject_title']); ?>')" class="text-red-500 hover:text-red-700 transition text-sm">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Curriculum Pagination -->
                        <?php if ($curriculum_total_pages > 1): ?>
                        <div class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo (($curriculum_page - 1) * $items_per_page) + 1; ?> to <?php echo min($curriculum_page * $items_per_page, $curriculum_total); ?> of <?php echo $curriculum_total; ?> curriculum subjects
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($curriculum_page > 1): ?>
                                <a href="?requirements_page=<?php echo $requirements_page; ?>&curriculum_page=<?php echo $curriculum_page - 1; ?>" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Previous</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $curriculum_page - 2); $i <= min($curriculum_total_pages, $curriculum_page + 2); $i++): ?>
                                <a href="?requirements_page=<?php echo $requirements_page; ?>&curriculum_page=<?php echo $i; ?>" class="px-3 py-2 text-sm rounded transition <?php echo $i == $curriculum_page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>

                                <?php if ($curriculum_page < $curriculum_total_pages): ?>
                                <a href="?requirements_page=<?php echo $requirements_page; ?>&curriculum_page=<?php echo $curriculum_page + 1; ?>" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- Edit Requirement Modal -->
    <div id="editRequirementModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-seait-dark">Edit Requirement</h3>
                    <button onclick="closeEditRequirementModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="editRequirementForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="edit_requirement">
                    <input type="hidden" name="requirement_id" id="edit_requirement_id">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course <span class="text-red-500">*</span></label>
                        <select name="course_id" id="edit_requirement_course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Course</option>
                            <?php mysqli_data_seek($courses_result, 0); ?>
                            <?php while($course = mysqli_fetch_assoc($courses_result)): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['college_name']); ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Requirement Type <span class="text-red-500">*</span></label>
                            <select name="requirement_type" id="edit_requirement_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select Type</option>
                                <option value="admission">Admission</option>
                                <option value="graduation">Graduation</option>
                                <option value="prerequisite">Prerequisite</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" id="edit_requirement_title" required placeholder="Enter requirement title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="edit_requirement_description" rows="3" placeholder="Enter requirement description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeEditRequirementModal()" class="px-4 py-2 text-gray-600 bg-gray-200 rounded-md hover:bg-gray-300 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600 transition">
                            Update Requirement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Curriculum Modal -->
    <div id="editCurriculumModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-seait-dark">Edit Curriculum Subject</h3>
                    <button onclick="closeEditCurriculumModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="editCurriculumForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="edit_curriculum">
                    <input type="hidden" name="curriculum_id" id="edit_curriculum_id">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course <span class="text-red-500">*</span></label>
                        <select name="course_id" id="edit_curriculum_course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Course</option>
                            <?php mysqli_data_seek($courses_result, 0); ?>
                            <?php while($course = mysqli_fetch_assoc($courses_result)): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['college_name']); ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year Level <span class="text-red-500">*</span></label>
                            <select name="year_level" id="edit_curriculum_year_level" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select Year</option>
                                <option value="first_year">First Year</option>
                                <option value="second_year">Second Year</option>
                                <option value="third_year">Third Year</option>
                                <option value="fourth_year">Fourth Year</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Semester <span class="text-red-500">*</span></label>
                            <select name="semester" id="edit_curriculum_semester" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select Semester</option>
                                <option value="first_semester">First Semester</option>
                                <option value="second_semester">Second Semester</option>
                                <option value="summer">Summer</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject Code <span class="text-red-500">*</span></label>
                            <input type="text" name="subject_code" id="edit_curriculum_subject_code" required placeholder="e.g., MATH101" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject Title <span class="text-red-500">*</span></label>
                            <input type="text" name="subject_title" id="edit_curriculum_subject_title" required placeholder="Enter subject title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prerequisite Subject</label>
                        <select name="prerequisite_id" id="edit_prerequisite_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Prerequisite Subject (Optional)</option>
                            <!-- Prerequisite options will be populated here -->
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Units</label>
                            <input type="number" name="units" id="edit_curriculum_units" step="0.1" value="3.0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lecture Hours</label>
                            <input type="number" name="lecture_hours" id="edit_curriculum_lecture_hours" value="3" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Laboratory Hours</label>
                            <input type="number" name="laboratory_hours" id="edit_curriculum_laboratory_hours" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="curriculum_description" id="edit_curriculum_description" rows="3" placeholder="Enter subject description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeEditCurriculumModal()" class="px-4 py-2 text-gray-600 bg-gray-200 rounded-md hover:bg-gray-300 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600 transition">
                            Update Curriculum
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2" id="deleteModalTitle">Delete Item</h3>
                        <p class="text-gray-600 mb-4" id="deleteModalMessage">Are you sure you want to delete this item? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1" id="deleteWarningList">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Item will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible on the website
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deleteForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" id="deleteAction">
                        <input type="hidden" name="requirement_id" id="deleteRequirementId">
                        <input type="hidden" name="curriculum_id" id="deleteCurriculumId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteModal()"
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
        // Tab Switching Function
        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });

            // Show selected tab content
            const selectedContent = document.getElementById(tabName + '-content');
            if (selectedContent) {
                selectedContent.classList.remove('hidden');
            }

            // Add active class to selected tab button
            const selectedButton = document.getElementById(tabName + '-tab');
            if (selectedButton) {
                selectedButton.classList.add('active');
            }
        }

        function toggleForm(formId) {
            const form = document.getElementById(formId);
            const toggleIcon = document.getElementById(formId.replace('-form', '-toggle-icon'));

            if (form.style.display === 'none') {
                form.style.display = 'block';
                toggleIcon.classList.remove('fa-chevron-up');
                toggleIcon.classList.add('fa-chevron-down');
            } else {
                form.style.display = 'none';
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-up');
            }
        }

        // Initialize forms as expanded by default
        document.addEventListener('DOMContentLoaded', function() {
            // Forms start expanded, so no need to hide them initially
        });

        // Edit Requirement Functions
        function editRequirement(requirementId) {
            // Fetch requirement data via AJAX
            fetch(`get_requirement.php?id=${requirementId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const requirement = data.requirement;
                        document.getElementById('edit_requirement_id').value = requirement.id;
                        document.getElementById('edit_requirement_course_id').value = requirement.course_id;
                        document.getElementById('edit_requirement_type').value = requirement.requirement_type;
                        document.getElementById('edit_requirement_title').value = requirement.title;
                        document.getElementById('edit_requirement_description').value = requirement.description;

                        document.getElementById('editRequirementModal').classList.remove('hidden');
                    } else {
                        alert('Error loading requirement data: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading requirement data');
                });
        }

        function closeEditRequirementModal() {
            document.getElementById('editRequirementModal').classList.add('hidden');
        }

        // Edit Curriculum Functions
        function editCurriculum(subjectId) {
            // Fetch curriculum data via AJAX
            fetch(`get_curriculum.php?id=${subjectId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const curriculum = data.curriculum;
                        document.getElementById('edit_curriculum_id').value = curriculum.id;
                        document.getElementById('edit_curriculum_course_id').value = curriculum.course_id;
                        document.getElementById('edit_curriculum_year_level').value = curriculum.year_level;
                        document.getElementById('edit_curriculum_semester').value = curriculum.semester;
                        document.getElementById('edit_curriculum_subject_code').value = curriculum.subject_code;
                        document.getElementById('edit_curriculum_subject_title').value = curriculum.subject_title;
                        document.getElementById('edit_curriculum_units').value = curriculum.units;
                        document.getElementById('edit_curriculum_lecture_hours').value = curriculum.lecture_hours;
                        document.getElementById('edit_curriculum_laboratory_hours').value = curriculum.laboratory_hours;
                        document.getElementById('edit_curriculum_description').value = curriculum.description;

                        // Load prerequisites for the course first, then set the selected value
                        loadPrerequisites(curriculum.course_id, document.getElementById('edit_prerequisite_id')).then(() => {
                            // Set the prerequisite value after the dropdown is populated
                            if (curriculum.prerequisite_id) {
                                document.getElementById('edit_prerequisite_id').value = curriculum.prerequisite_id;
                            }
                        });

                        document.getElementById('editCurriculumModal').classList.remove('hidden');
                    } else {
                        alert('Error loading curriculum data: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading curriculum data');
                });
        }

        function closeEditCurriculumModal() {
            document.getElementById('editCurriculumModal').classList.add('hidden');
        }

        // Delete Functions
        function deleteRequirement(requirementId, requirementTitle) {
            document.getElementById('deleteModalTitle').textContent = 'Delete Requirement';
            document.getElementById('deleteModalMessage').textContent = `Are you sure you want to delete the requirement "${requirementTitle}"? This action cannot be undone.`;
            document.getElementById('deleteWarningList').innerHTML = `
                <li class="flex items-center">
                    <i class="fas fa-trash mr-2 text-red-500"></i>
                    Item will be permanently removed
                </li>
                <li class="flex items-center">
                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                    No longer visible on the website
                </li>
                <li class="flex items-center">
                    <i class="fas fa-undo mr-2 text-red-500"></i>
                    Cannot be recovered
                </li>
            `;
            document.getElementById('deleteAction').value = 'delete_requirement';
            document.getElementById('deleteRequirementId').value = requirementId;
            document.getElementById('deleteCurriculumId').value = '';
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function deleteCurriculum(curriculumId, subjectCode, subjectTitle) {
            document.getElementById('deleteModalTitle').textContent = 'Delete Curriculum Subject';
            document.getElementById('deleteModalMessage').textContent = `Are you sure you want to delete the curriculum subject "${subjectCode} - ${subjectTitle}"? This action cannot be undone.`;
            document.getElementById('deleteWarningList').innerHTML = `
                <li class="flex items-center">
                    <i class="fas fa-trash mr-2 text-red-500"></i>
                    Item will be permanently removed
                </li>
                <li class="flex items-center">
                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                    No longer visible on the website
                </li>
                <li class="flex items-center">
                    <i class="fas fa-undo mr-2 text-red-500"></i>
                    Cannot be recovered
                </li>
            `;
            document.getElementById('deleteAction').value = 'delete_curriculum';
            document.getElementById('deleteCurriculumId').value = curriculumId;
            document.getElementById('deleteRequirementId').value = '';
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editRequirementModal = document.getElementById('editRequirementModal');
            const editCurriculumModal = document.getElementById('editCurriculumModal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target === editRequirementModal) {
                closeEditRequirementModal();
            }
            if (event.target === editCurriculumModal) {
                closeEditCurriculumModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }

        // Prerequisite Dropdown Functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Setting up prerequisite functionality');

            const courseSelect = document.getElementById('add_course_id');
            const prerequisiteSelect = document.getElementById('prerequisite_id');
            const curriculumCourseSelect = document.getElementById('curriculum_course_id');
            const editCourseSelect = document.getElementById('edit_curriculum_course_id');
            const editPrerequisiteSelect = document.getElementById('edit_prerequisite_id');

            console.log('Course select element (add form):', courseSelect);
            console.log('Prerequisite select element:', prerequisiteSelect);
            console.log('Curriculum course select element:', curriculumCourseSelect);
            console.log('Edit course select element:', editCourseSelect);
            console.log('Edit prerequisite select element:', editPrerequisiteSelect);

            // Load prerequisites when course is selected (Add form)
            if (courseSelect) {
                console.log('Course select found, adding change event listener');

                // Remove any existing event listeners
                courseSelect.removeEventListener('change', handleCourseChange);

                // Add new event listener
                courseSelect.addEventListener('change', handleCourseChange);

                function handleCourseChange() {
                    console.log('Course select changed to:', this.value);
                    if (this.value) {
                        console.log('Loading prerequisites for course ID:', this.value);
                        loadPrerequisites(this.value, prerequisiteSelect);
                    } else {
                        console.log('No course selected, clearing prerequisite dropdown');
                        prerequisiteSelect.innerHTML = '<option value="">Select Prerequisite Subject (Optional)</option>';
                    }
                }

                // Also add click event as backup
                courseSelect.addEventListener('click', function() {
                    console.log('Course select clicked');
                });

            } else {
                console.log('Course select not found');
            }

            // Load prerequisites when course is selected (Curriculum form)
            if (curriculumCourseSelect) {
                console.log('Curriculum course select found, adding change event listener');
                curriculumCourseSelect.addEventListener('change', function() {
                    console.log('Curriculum course select changed to:', this.value);
                    if (this.value) {
                        console.log('Loading prerequisites for curriculum course ID:', this.value);
                        loadPrerequisites(this.value, prerequisiteSelect);
                    } else {
                        console.log('No curriculum course selected, clearing prerequisite dropdown');
                        prerequisiteSelect.innerHTML = '<option value="">Select Prerequisite Subject (Optional)</option>';
                    }
                });
            } else {
                console.log('Curriculum course select not found');
            }

            // Load prerequisites when course is selected (Edit form)
            if (editCourseSelect) {
                console.log('Edit course select found, adding change event listener');
                editCourseSelect.addEventListener('change', function() {
                    console.log('Edit course select changed to:', this.value);
                    if (this.value) {
                        console.log('Loading prerequisites for edit course ID:', this.value);
                        loadPrerequisites(this.value, editPrerequisiteSelect);
                    } else {
                        console.log('No edit course selected, clearing prerequisite dropdown');
                        editPrerequisiteSelect.innerHTML = '<option value="">Select Prerequisite Subject (Optional)</option>';
                    }
                });
            } else {
                console.log('Edit course select not found');
            }
        });

        function loadPrerequisites(courseId, targetSelect) {
            console.log('loadPrerequisites called with courseId:', courseId, 'targetSelect:', targetSelect);

            if (!courseId) {
                console.log('No courseId provided, clearing dropdown');
                targetSelect.innerHTML = '<option value="">Select Prerequisite Subject (Optional)</option>';
                return Promise.resolve();
            }

            console.log('Fetching prerequisites for course:', courseId);
            return fetch('get_prerequisites.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `course_id=${courseId}&load_all=1`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    console.log('Populating dropdown with', data.prerequisites.length, 'subjects');
                    populatePrerequisiteDropdown(data.prerequisites, targetSelect);
                } else {
                    console.error('Error loading prerequisites:', data.message);
                    targetSelect.innerHTML = '<option value="">Error loading prerequisites</option>';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                targetSelect.innerHTML = '<option value="">Error loading prerequisites</option>';
            });
        }

        function populatePrerequisiteDropdown(prerequisites, targetSelect) {
            console.log('populatePrerequisiteDropdown called with:', prerequisites, 'targetSelect:', targetSelect);

            targetSelect.innerHTML = '<option value="">Select Prerequisite Subject (Optional)</option>';

            if (prerequisites.length === 0) {
                console.log('No prerequisites found, adding disabled option');
                targetSelect.innerHTML += '<option value="" disabled>No subjects available in this course</option>';
            } else {
                console.log('Adding', prerequisites.length, 'subjects to dropdown');
                prerequisites.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = `${subject.subject_code} - ${subject.subject_title} (${subject.year_level} • ${subject.semester})`;
                    targetSelect.appendChild(option);
                    console.log('Added option:', option.textContent);
                });
            }
        }

        // Test function - can be called from browser console
        function testPrerequisiteLoading(courseId = 1) {
            console.log('Testing prerequisite loading for course ID:', courseId);
            const prerequisiteSelect = document.getElementById('prerequisite_id');
            if (prerequisiteSelect) {
                loadPrerequisites(courseId, prerequisiteSelect);
            } else {
                console.error('Prerequisite select element not found');
            }
        }

        function loadPrerequisitesForSelectedCourse() {
            const courseSelect = document.getElementById('add_course_id');
            const prerequisiteSelect = document.getElementById('prerequisite_id');
            if (courseSelect && prerequisiteSelect) {
                const selectedCourseId = courseSelect.value;
                if (selectedCourseId) {
                    loadPrerequisites(selectedCourseId, prerequisiteSelect);
                } else {
                    alert('Please select a course first.');
                }
            } else {
                console.error('Course select or Prerequisite select not found.');
            }
        }

        // --- Table Search Functionality ---
        function setupTableSearch(inputId, tableId) {
            const input = document.getElementById(inputId);
            const table = document.getElementById(tableId);
            if (!input || !table) return;
            input.addEventListener('input', function() {
                const filter = input.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            setupTableSearch('requirementsSearch', 'requirementsTable');
            setupTableSearch('curriculumSearch', 'curriculumTable');
        });
    </script>
</body>
</html>