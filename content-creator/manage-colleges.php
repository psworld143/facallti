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
            case 'add_college':
                $name = trim($_POST['name']);
                $short_name = trim($_POST['short_name']);
                $description = trim($_POST['description']);
                $color_theme = trim($_POST['color_theme']);
                $sort_order = (int)$_POST['sort_order'];

                // Handle logo upload
                $logo_url = '';
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
                    $upload_dir = '../assets/images/colleges/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'college_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;

                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                            $logo_url = 'assets/images/colleges/' . $filename;
                        } else {
                            $error = 'Failed to upload logo.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, GIF, and SVG are allowed.';
                    }
                }

                if (empty($name) || empty($short_name)) {
                    $error = 'College name and short name are required.';
                } else {
                    $query = "INSERT INTO colleges (name, short_name, description, color_theme, logo_url, sort_order, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'sssssii', $name, $short_name, $description, $color_theme, $logo_url, $sort_order, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'College added successfully!';
                    } else {
                        $error = 'Error adding college: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'add_course':
                $college_id = (int)$_POST['college_id'];
                $name = trim($_POST['course_name']);
                $short_name = trim($_POST['course_short_name']);
                $description = trim($_POST['course_description']);
                $level = $_POST['level'];
                $duration = trim($_POST['duration']);
                $credits = (int)$_POST['credits'];
                $sort_order = (int)$_POST['course_sort_order'];

                // Handle logo upload
                $logo_url = '';
                if (isset($_FILES['course_logo']) && $_FILES['course_logo']['error'] === 0) {
                    $upload_dir = '../assets/images/courses/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['course_logo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'course_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;

                        if (move_uploaded_file($_FILES['course_logo']['tmp_name'], $upload_path)) {
                            $logo_url = 'assets/images/courses/' . $filename;
                        } else {
                            $error = 'Failed to upload logo.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, GIF, and SVG are allowed.';
                    }
                }

                if (empty($name) || empty($college_id)) {
                    $error = 'Course name and college are required.';
                } else {
                    $query = "INSERT INTO courses (college_id, name, short_name, description, logo_url, level, duration, credits, sort_order, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'isssssssii', $college_id, $name, $short_name, $description, $logo_url, $level, $duration, $credits, $sort_order, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Course added successfully!';
                    } else {
                        $error = 'Error adding course: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'update_college':
                $college_id = (int)$_POST['college_id'];
                $name = trim($_POST['name']);
                $short_name = trim($_POST['short_name']);
                $description = trim($_POST['description']);
                $color_theme = trim($_POST['color_theme']);
                $sort_order = (int)$_POST['sort_order'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                // Handle logo upload for update
                $logo_url = $_POST['current_logo']; // Keep existing logo if no new one uploaded
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
                    $upload_dir = '../assets/images/colleges/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'college_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;

                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                            $logo_url = 'assets/images/colleges/' . $filename;
                        } else {
                            $error = 'Failed to upload logo.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, GIF, and SVG are allowed.';
                    }
                }

                $query = "UPDATE colleges SET name = ?, short_name = ?, description = ?, color_theme = ?, logo_url = ?, sort_order = ?, is_active = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'sssssiii', $name, $short_name, $description, $color_theme, $logo_url, $sort_order, $is_active, $college_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'College updated successfully!';
                } else {
                    $error = 'Error updating college: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'update_course':
                $course_id = (int)$_POST['course_id'];
                $college_id = (int)$_POST['college_id'];
                $name = trim($_POST['course_name']);
                $short_name = trim($_POST['course_short_name']);
                $description = trim($_POST['course_description']);
                $level = $_POST['level'];
                $duration = trim($_POST['duration']);
                $credits = (int)$_POST['credits'];
                $sort_order = (int)$_POST['course_sort_order'];
                $is_active = isset($_POST['course_is_active']) ? 1 : 0;

                // Handle logo upload for update
                $logo_url = $_POST['current_course_logo']; // Keep existing logo if no new one uploaded
                if (isset($_FILES['course_logo']) && $_FILES['course_logo']['error'] === 0) {
                    $upload_dir = '../assets/images/courses/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['course_logo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'course_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;

                        if (move_uploaded_file($_FILES['course_logo']['tmp_name'], $upload_path)) {
                            $logo_url = 'assets/images/courses/' . $filename;
                        } else {
                            $error = 'Failed to upload logo.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, GIF, and SVG are allowed.';
                    }
                }

                $query = "UPDATE courses SET college_id = ?, name = ?, short_name = ?, description = ?, logo_url = ?, level = ?, duration = ?, credits = ?, sort_order = ?, is_active = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'isssssssiii', $college_id, $name, $short_name, $description, $logo_url, $level, $duration, $credits, $sort_order, $is_active, $course_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Course updated successfully!';
                } else {
                    $error = 'Error updating course: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'delete_college':
                $college_id = (int)$_POST['college_id'];

                // First, delete all courses associated with this college
                $delete_courses_query = "DELETE FROM courses WHERE college_id = ?";
                $stmt = mysqli_prepare($conn, $delete_courses_query);
                mysqli_stmt_bind_param($stmt, "i", $college_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // Then delete the college
                $delete_college_query = "DELETE FROM colleges WHERE id = ?";
                $stmt = mysqli_prepare($conn, $delete_college_query);
                mysqli_stmt_bind_param($stmt, "i", $college_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'College and all associated courses deleted successfully!';
                } else {
                    $error = 'Error deleting college: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'delete_course':
                $course_id = (int)$_POST['course_id'];

                // First, delete all requirements and curriculum associated with this course
                $delete_requirements_query = "DELETE FROM course_requirements WHERE course_id = ?";
                $stmt = mysqli_prepare($conn, $delete_requirements_query);
                mysqli_stmt_bind_param($stmt, "i", $course_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $delete_curriculum_query = "DELETE FROM course_curriculum WHERE course_id = ?";
                $stmt = mysqli_prepare($conn, $delete_curriculum_query);
                mysqli_stmt_bind_param($stmt, "i", $course_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // Then delete the course
                $delete_course_query = "DELETE FROM courses WHERE id = ?";
                $stmt = mysqli_prepare($conn, $delete_course_query);
                mysqli_stmt_bind_param($stmt, "i", $course_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Course and all associated requirements and curriculum deleted successfully!';
                } else {
                    $error = 'Error deleting course: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Fetch colleges
$colleges_query = "SELECT * FROM colleges ORDER BY sort_order ASC, name ASC";
$colleges_result = mysqli_query($conn, $colleges_query);

// Fetch courses with college information
$courses_query = "SELECT c.*, co.name as college_name, co.short_name as college_short_name
                  FROM courses c
                  JOIN colleges co ON c.college_id = co.id
                  ORDER BY co.sort_order ASC, c.sort_order ASC, c.name ASC";
$courses_result = mysqli_query($conn, $courses_query);

// Pagination settings
$items_per_page = 10;
$colleges_page = isset($_GET['colleges_page']) ? (int)$_GET['colleges_page'] : 1;
$courses_page = isset($_GET['courses_page']) ? (int)$_GET['courses_page'] : 1;

// Get total counts
$colleges_count_query = "SELECT COUNT(*) as total FROM colleges";
$colleges_count_result = mysqli_query($conn, $colleges_count_query);
$colleges_total = mysqli_fetch_assoc($colleges_count_result)['total'];
$colleges_total_pages = ceil($colleges_total / $items_per_page);

$courses_count_query = "SELECT COUNT(*) as total FROM courses";
$courses_count_result = mysqli_query($conn, $courses_count_query);
$courses_total = mysqli_fetch_assoc($courses_count_result)['total'];
$courses_total_pages = ceil($courses_total / $items_per_page);

// Fetch paginated colleges
$colleges_offset = ($colleges_page - 1) * $items_per_page;
$colleges_query = "SELECT * FROM colleges ORDER BY sort_order ASC, name ASC LIMIT $items_per_page OFFSET $colleges_offset";
$colleges_result = mysqli_query($conn, $colleges_query);

// Fetch paginated courses
$courses_offset = ($courses_page - 1) * $items_per_page;
$courses_query = "SELECT c.*, co.name as college_name, co.short_name as college_short_name
                  FROM courses c
                  JOIN colleges co ON c.college_id = co.id
                  ORDER BY co.sort_order ASC, c.sort_order ASC, c.name ASC
                  LIMIT $items_per_page OFFSET $courses_offset";
$courses_result = mysqli_query($conn, $courses_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Colleges & Courses - SEAIT Content Creator</title>
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
        @keyframes bounceIn {
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
            animation: bounceIn 0.6s ease-out;
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
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Manage Colleges & Courses</h1>
                    <p class="text-gray-600">Create and manage colleges, courses, and their details</p>
                </div>

            <!-- Information Section -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">College and Course Management</h3>
                        <div class="text-sm text-blue-700 space-y-1">
                            <p><strong>Colleges:</strong> Add new colleges with logos, color themes, and descriptions.</p>
                            <p><strong>Courses:</strong> Add courses to existing colleges with detailed information.</p>
                            <p><strong>Organization:</strong> Use the toggle buttons to collapse/expand forms as needed. Both forms are side-by-side on desktop for easy access.</p>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Forms Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-6 lg:mb-8">
            <!-- Add College Form -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-full mr-3">
                            <i class="fas fa-university text-purple-600"></i>
                        </div>
                        <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add New College</h2>
                    </div>
                    <button type="button" onclick="toggleForm('college-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                        <i class="fas fa-chevron-down" id="college-toggle-icon"></i>
                    </button>
                </div>
                <form method="POST" id="college-form" class="space-y-4" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_college">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">College Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required placeholder="Enter college name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Short Name <span class="text-red-500">*</span></label>
                            <input type="text" name="short_name" required placeholder="e.g., CBGG" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Color Theme</label>
                        <div class="flex items-center space-x-3">
                            <input type="color" name="color_theme" value="#FF6B35" class="w-12 h-10 border border-gray-300 rounded-md">
                            <span class="text-sm text-gray-600">Choose a color theme for the college</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" placeholder="Enter college description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                        <input type="file" name="logo" id="collegeLogo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" onchange="previewLogo(this)">
                        <p class="text-xs text-gray-500 mt-1">Recommended size: 200x200px. Supported formats: JPG, PNG, GIF, SVG.</p>
                        <div id="logoPreview" class="mt-2 hidden">
                            <img id="previewImage" src="" alt="Logo Preview" class="w-16 h-16 lg:w-20 lg:h-20 object-contain border border-gray-300 rounded">
                            <p class="text-xs text-gray-500 mt-1">Logo preview</p>
                        </div>
                        <div id="logoPlaceholder" class="mt-2">
                            <div class="w-16 h-16 lg:w-20 lg:h-20 bg-gray-100 border border-gray-300 rounded flex items-center justify-center">
                                <i class="fas fa-university text-gray-400 text-lg"></i>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">No logo selected</p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                            <i class="fas fa-plus mr-2"></i>Add College
                        </button>
                    </div>
                </form>
            </div>

            <!-- Add Course Form -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-indigo-100 rounded-full mr-3">
                            <i class="fas fa-graduation-cap text-indigo-600"></i>
                        </div>
                        <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add New Course</h2>
                    </div>
                    <button type="button" onclick="toggleForm('course-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                        <i class="fas fa-chevron-down" id="course-toggle-icon"></i>
                    </button>
                </div>
                <form method="POST" id="course-form" class="space-y-4">
                    <input type="hidden" name="action" value="add_course">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">College <span class="text-red-500">*</span></label>
                        <select name="college_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select College</option>
                            <?php mysqli_data_seek($colleges_result, 0); ?>
                            <?php while($college = mysqli_fetch_assoc($colleges_result)): ?>
                            <option value="<?php echo $college['id']; ?>"><?php echo htmlspecialchars($college['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Course Name <span class="text-red-500">*</span></label>
                            <input type="text" name="course_name" required placeholder="Enter course name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Short Name</label>
                            <input type="text" name="course_short_name" placeholder="e.g., BSHM" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Level <span class="text-red-500">*</span></label>
                            <select name="level" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select Level</option>
                                <option value="undergraduate">Undergraduate</option>
                                <option value="graduate">Graduate</option>
                                <option value="postgraduate">Postgraduate</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="course_sort_order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration</label>
                            <input type="text" name="duration" value="4 years" placeholder="e.g., 4 years" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Credits</label>
                            <input type="number" name="credits" value="144" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="course_description" rows="3" placeholder="Enter course description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                        <input type="file" name="course_logo" id="courseLogo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" onchange="previewCourseLogo(this)">
                        <p class="text-xs text-gray-500 mt-1">Recommended size: 200x200px. Supported formats: JPG, PNG, GIF, SVG.</p>
                        <div id="courseLogoPreview" class="mt-2 hidden">
                            <img id="coursePreviewImage" src="" alt="Logo Preview" class="w-16 h-16 lg:w-20 lg:h-20 object-contain border border-gray-300 rounded">
                            <p class="text-xs text-gray-500 mt-1">Logo preview</p>
                        </div>
                        <div id="courseLogoPlaceholder" class="mt-2">
                            <div class="w-16 h-16 lg:w-20 lg:h-20 bg-gray-100 border border-gray-300 rounded flex items-center justify-center">
                                <i class="fas fa-graduation-cap text-gray-400 text-lg"></i>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">No logo selected</p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                            <i class="fas fa-plus mr-2"></i>Add Course
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Colleges List -->
        <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-6 lg:mb-8">
            <h2 class="text-lg lg:text-xl font-semibold text-seait-dark mb-4">Manage Colleges</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">College</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Short Name</th>
                            <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Color</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php mysqli_data_seek($colleges_result, 0); ?>
                        <?php while($college = mysqli_fetch_assoc($colleges_result)): ?>
                        <tr data-college-id="<?php echo $college['id']; ?>" data-college-name="<?php echo htmlspecialchars($college['name']); ?>" data-college-short="<?php echo htmlspecialchars($college['short_name']); ?>" data-college-description="<?php echo htmlspecialchars($college['description']); ?>" data-college-color="<?php echo htmlspecialchars($college['color_theme']); ?>" data-college-sort="<?php echo $college['sort_order']; ?>" data-college-active="<?php echo $college['is_active']; ?>" data-college-logo="<?php echo htmlspecialchars($college['logo_url']); ?>">
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($college['logo_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($college['logo_url']); ?>" alt="<?php echo htmlspecialchars($college['name']); ?> Logo" class="h-8 w-8 lg:h-12 lg:w-12 object-contain rounded">
                                <?php else: ?>
                                <div class="h-8 w-8 lg:h-12 lg:w-12 bg-gray-200 rounded flex items-center justify-center">
                                    <i class="fas fa-university text-gray-400 text-xs lg:text-sm"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 lg:px-6 py-4">
                                <div class="text-xs lg:text-sm font-medium text-gray-900"><?php echo htmlspecialchars($college['name']); ?></div>
                                <div class="text-xs text-gray-500 hidden sm:block break-words max-w-xs"><?php echo htmlspecialchars($college['description']); ?></div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs lg:text-sm text-gray-900"><?php echo htmlspecialchars($college['short_name']); ?></td>
                            <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap">
                                <div class="w-4 h-4 lg:w-6 lg:h-6 rounded-full" style="background-color: <?php echo htmlspecialchars($college['color_theme']); ?>"></div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $college['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $college['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs lg:text-sm font-medium">
                                <button onclick="editCollege(<?php echo $college['id']; ?>)" class="text-seait-orange hover:text-orange-600 mr-3">Edit</button>
                                <button onclick="confirmDeleteCollege(<?php echo $college['id']; ?>, '<?php echo htmlspecialchars($college['name']); ?>')" class="text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Colleges Pagination -->
            <?php if ($colleges_total_pages > 1): ?>
            <div class="mt-6 flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                <div class="text-xs lg:text-sm text-gray-700">
                    Showing <?php echo (($colleges_page - 1) * $items_per_page) + 1; ?> to <?php echo min($colleges_page * $items_per_page, $colleges_total); ?> of <?php echo $colleges_total; ?> colleges
                </div>
                <div class="flex space-x-2">
                    <?php if ($colleges_page > 1): ?>
                    <a href="?colleges_page=<?php echo $colleges_page - 1; ?>&courses_page=<?php echo $courses_page; ?>" class="px-3 py-2 text-xs lg:text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $colleges_page - 2); $i <= min($colleges_total_pages, $colleges_page + 2); $i++): ?>
                    <a href="?colleges_page=<?php echo $i; ?>&courses_page=<?php echo $courses_page; ?>" class="px-3 py-2 text-xs lg:text-sm rounded transition <?php echo $i == $colleges_page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($colleges_page < $colleges_total_pages): ?>
                    <a href="?colleges_page=<?php echo $colleges_page + 1; ?>&courses_page=<?php echo $courses_page; ?>" class="px-3 py-2 text-xs lg:text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Courses List -->
        <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
            <h2 class="text-lg lg:text-xl font-semibold text-seait-dark mb-4">Manage Courses</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">College</th>
                            <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                            <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php mysqli_data_seek($courses_result, 0); ?>
                        <?php while($course = mysqli_fetch_assoc($courses_result)): ?>
                        <tr data-course-id="<?php echo $course['id']; ?>" data-course-name="<?php echo htmlspecialchars($course['name']); ?>" data-course-short="<?php echo htmlspecialchars($course['short_name']); ?>" data-course-description="<?php echo htmlspecialchars($course['description']); ?>" data-course-level="<?php echo htmlspecialchars($course['level']); ?>" data-course-duration="<?php echo htmlspecialchars($course['duration']); ?>" data-course-credits="<?php echo $course['credits']; ?>" data-course-sort="<?php echo $course['sort_order']; ?>" data-course-active="<?php echo $course['is_active']; ?>" data-course-college="<?php echo $course['college_id']; ?>" data-course-logo="<?php echo htmlspecialchars($course['logo_url']); ?>">
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($course['logo_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($course['logo_url']); ?>" alt="<?php echo htmlspecialchars($course['name']); ?> Logo" class="h-8 w-8 lg:h-12 lg:w-12 object-contain rounded">
                                <?php else: ?>
                                <div class="h-8 w-8 lg:h-12 lg:w-12 bg-gray-200 rounded flex items-center justify-center">
                                    <i class="fas fa-graduation-cap text-gray-400 text-xs lg:text-sm"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 lg:px-6 py-4">
                                <div class="text-xs lg:text-sm font-medium text-gray-900 break-words max-w-xs"><?php echo htmlspecialchars($course['name']); ?></div>
                                <div class="text-xs text-gray-500 hidden sm:block break-words max-w-xs"><?php echo htmlspecialchars($course['short_name']); ?></div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs lg:text-sm text-gray-900"><?php echo htmlspecialchars($course['college_name']); ?></td>
                            <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap text-xs lg:text-sm text-gray-900"><?php echo ucfirst(htmlspecialchars($course['level'])); ?></td>
                            <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap text-xs lg:text-sm text-gray-900"><?php echo htmlspecialchars($course['duration']); ?></td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $course['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs lg:text-sm font-medium">
                                <button onclick="editCourse(<?php echo $course['id']; ?>)" class="text-seait-orange hover:text-orange-600 mr-3">Edit</button>
                                <button onclick="confirmDeleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['name']); ?>')" class="text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Courses Pagination -->
            <?php if ($courses_total_pages > 1): ?>
            <div class="mt-6 flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                <div class="text-xs lg:text-sm text-gray-700">
                    Showing <?php echo (($courses_page - 1) * $items_per_page) + 1; ?> to <?php echo min($courses_page * $items_per_page, $courses_total); ?> of <?php echo $courses_total; ?> courses
                </div>
                <div class="flex space-x-2">
                    <?php if ($courses_page > 1): ?>
                    <a href="?colleges_page=<?php echo $colleges_page; ?>&courses_page=<?php echo $courses_page - 1; ?>" class="px-3 py-2 text-xs lg:text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $courses_page - 2); $i <= min($courses_total_pages, $courses_page + 2); $i++): ?>
                    <a href="?colleges_page=<?php echo $colleges_page; ?>&courses_page=<?php echo $i; ?>" class="px-3 py-2 text-xs lg:text-sm rounded transition <?php echo $i == $courses_page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($courses_page < $courses_total_pages): ?>
                    <a href="?colleges_page=<?php echo $colleges_page; ?>&courses_page=<?php echo $courses_page + 1; ?>" class="px-3 py-2 text-xs lg:text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
            </div>
        </div>
    </div>

    <!-- Edit College Modal -->
    <div id="editCollegeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-4 sm:top-20 mx-auto p-4 sm:p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit College</h3>
                <form id="editCollegeForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_college">
                    <input type="hidden" name="college_id" id="editCollegeId">
                    <input type="hidden" name="current_logo" id="editCurrentLogo">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">College Name *</label>
                            <input type="text" name="name" id="editCollegeName" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Short Name *</label>
                            <input type="text" name="short_name" id="editCollegeShortName" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                            <input type="color" name="color_theme" id="editCollegeColor" value="#FF6B35" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="editCollegeSortOrder" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" id="editCollegeDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                            <input type="file" name="logo" id="editCollegeLogo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" onchange="previewEditLogo(this)">
                            <p class="text-xs text-gray-500 mt-1">Recommended size: 200x200px. Supported formats: JPG, PNG, GIF, SVG.</p>
                            <div id="editLogoPreview" class="mt-2">
                                <img id="editPreviewImage" src="" alt="Logo Preview" class="w-20 h-20 object-contain border border-gray-300 rounded">
                                <p class="text-xs text-gray-500 mt-1">Current logo</p>
                            </div>
                            <div id="editLogoPlaceholder" class="mt-2">
                                <div class="w-20 h-20 bg-gray-100 border border-gray-300 rounded flex items-center justify-center">
                                    <i class="fas fa-university text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">No logo uploaded</p>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" id="editCollegeIsActive" class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <span class="ml-2 block text-sm text-gray-900">Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 mt-6">
                        <button type="button" onclick="closeEditCollegeModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition w-full sm:w-auto">Cancel</button>
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition w-full sm:w-auto">Update College</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete College Confirmation Modal -->
    <div id="deleteCollegeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete College</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteCollegeName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    College will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-link mr-2 text-red-500"></i>
                                    All associated courses will be deleted
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
                    <form id="deleteCollegeForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_college">
                        <input type="hidden" name="college_id" id="deleteCollegeId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteCollegeModal()"
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

    <!-- Delete Course Confirmation Modal -->
    <div id="deleteCourseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Course</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteCourseName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Course will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-link mr-2 text-red-500"></i>
                                    All requirements and curriculum will be deleted
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
                    <form id="deleteCourseForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_course">
                        <input type="hidden" name="course_id" id="deleteCourseId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteCourseModal()"
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

    <!-- Edit Course Modal -->
    <div id="editCourseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-4 sm:top-20 mx-auto p-4 sm:p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Course</h3>
                <form id="editCourseForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_course">
                    <input type="hidden" name="course_id" id="editCourseId">
                    <input type="hidden" name="current_course_logo" id="editCurrentCourseLogo">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Course Name *</label>
                            <input type="text" name="course_name" id="editCourseName" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Short Name *</label>
                            <input type="text" name="course_short_name" id="editCourseShortName" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">College *</label>
                            <select name="college_id" id="editCourseCollege" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select College</option>
                                <?php mysqli_data_seek($colleges_result, 0); ?>
                                <?php while($college = mysqli_fetch_assoc($colleges_result)): ?>
                                <option value="<?php echo $college['id']; ?>"><?php echo htmlspecialchars($college['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Level *</label>
                            <select name="level" id="editCourseLevel" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select Level</option>
                                <option value="undergraduate">Undergraduate</option>
                                <option value="graduate">Graduate</option>
                                <option value="postgraduate">Postgraduate</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Duration</label>
                            <input type="text" name="duration" id="editCourseDuration" placeholder="e.g., 4 years" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Credits</label>
                            <input type="number" name="credits" id="editCourseCredits" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="course_sort_order" id="editCourseSortOrder" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="course_description" id="editCourseDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                            <input type="file" name="course_logo" id="editCourseLogo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" onchange="previewEditCourseLogo(this)">
                            <p class="text-xs text-gray-500 mt-1">Recommended size: 200x200px. Supported formats: JPG, PNG, GIF, SVG.</p>
                            <div id="editCourseLogoPreview" class="mt-2">
                                <img id="editCoursePreviewImage" src="" alt="Logo Preview" class="w-20 h-20 object-contain border border-gray-300 rounded">
                                <p class="text-xs text-gray-500 mt-1">Current logo</p>
                            </div>
                            <div id="editCourseLogoPlaceholder" class="mt-2">
                                <div class="w-20 h-20 bg-gray-100 border border-gray-300 rounded flex items-center justify-center">
                                    <i class="fas fa-graduation-cap text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">No logo uploaded</p>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="course_is_active" id="editCourseIsActive" class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <span class="ml-2 block text-sm text-gray-900">Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 mt-6">
                        <button type="button" onclick="closeEditCourseModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition w-full sm:w-auto">Cancel</button>
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition w-full sm:w-auto">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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

        function previewLogo(input) {
            const previewImage = document.getElementById('previewImage');
            const logoPreview = document.getElementById('logoPreview');
            const logoPlaceholder = document.getElementById('logoPlaceholder');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    logoPreview.classList.remove('hidden');
                    logoPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                previewImage.src = '';
                logoPreview.classList.add('hidden');
                logoPlaceholder.classList.remove('hidden');
            }
        }

        function previewEditLogo(input) {
            const previewImage = document.getElementById('editPreviewImage');
            const editLogoPreview = document.getElementById('editLogoPreview');
            const editLogoPlaceholder = document.getElementById('editLogoPlaceholder');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    editLogoPreview.classList.remove('hidden');
                    editLogoPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                // If no file selected, show the current logo or placeholder
                const currentLogo = document.getElementById('editCurrentLogo').value;
                if (currentLogo) {
                    previewImage.src = '../' + currentLogo;
                    editLogoPreview.classList.remove('hidden');
                    editLogoPlaceholder.classList.add('hidden');
                } else {
                    previewImage.src = '';
                    editLogoPreview.classList.add('hidden');
                    editLogoPlaceholder.classList.remove('hidden');
                }
            }
        }

        function previewCourseLogo(input) {
            const previewImage = document.getElementById('coursePreviewImage');
            const courseLogoPreview = document.getElementById('courseLogoPreview');
            const courseLogoPlaceholder = document.getElementById('courseLogoPlaceholder');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    courseLogoPreview.classList.remove('hidden');
                    courseLogoPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                // If no file selected, show the current logo or placeholder
                const currentLogo = document.getElementById('editCurrentCourseLogo').value;
                if (currentLogo) {
                    previewImage.src = '../' + currentLogo;
                    courseLogoPreview.classList.remove('hidden');
                    courseLogoPlaceholder.classList.add('hidden');
                } else {
                    previewImage.src = '';
                    courseLogoPreview.classList.add('hidden');
                    courseLogoPlaceholder.classList.remove('hidden');
                }
            }
        }

        function previewEditCourseLogo(input) {
            const previewImage = document.getElementById('editCoursePreviewImage');
            const courseLogoPreview = document.getElementById('editCourseLogoPreview');
            const courseLogoPlaceholder = document.getElementById('editCourseLogoPlaceholder');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    courseLogoPreview.classList.remove('hidden');
                    courseLogoPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                // If no file selected, show the current logo or placeholder
                const currentLogo = document.getElementById('editCurrentCourseLogo').value;
                if (currentLogo) {
                    previewImage.src = '../' + currentLogo;
                    courseLogoPreview.classList.remove('hidden');
                    courseLogoPlaceholder.classList.add('hidden');
                } else {
                    previewImage.src = '';
                    courseLogoPreview.classList.add('hidden');
                    courseLogoPlaceholder.classList.remove('hidden');
                }
            }
        }

        function editCollege(collegeId) {
            // Fetch college details for editing
            const collegeRow = document.querySelector(`tr[data-college-id="${collegeId}"]`);
            const dataAttributes = collegeRow.dataset;

            document.getElementById('editCollegeId').value = collegeId;
            document.getElementById('editCollegeName').value = dataAttributes.collegeName;
            document.getElementById('editCollegeShortName').value = dataAttributes.collegeShort;
            document.getElementById('editCollegeColor').value = dataAttributes.collegeColor;
            document.getElementById('editCollegeSortOrder').value = dataAttributes.collegeSort;
            document.getElementById('editCollegeDescription').value = dataAttributes.collegeDescription;
            document.getElementById('editCurrentLogo').value = dataAttributes.collegeLogo; // Get current logo URL
            document.getElementById('editCollegeIsActive').checked = dataAttributes.collegeActive === '1'; // Check if active

            // Show current logo in preview
            const editPreviewImage = document.getElementById('editPreviewImage');
            const editLogoPreview = document.getElementById('editLogoPreview');
            const editLogoPlaceholder = document.getElementById('editLogoPlaceholder');
            if (dataAttributes.collegeLogo) {
                editPreviewImage.src = '../' + dataAttributes.collegeLogo;
                editLogoPreview.classList.remove('hidden');
                editLogoPlaceholder.classList.add('hidden');
            } else {
                editPreviewImage.src = '';
                editLogoPreview.classList.add('hidden');
                editLogoPlaceholder.classList.remove('hidden');
            }

            document.getElementById('editCollegeForm').action = 'manage-colleges.php'; // Set action to update
            document.getElementById('editCollegeForm').target = '_self'; // Open in the same window
            document.getElementById('editCollegeModal').classList.remove('hidden');
        }

        function editCourse(courseId) {
            // Fetch course details for editing
            const courseRow = document.querySelector(`tr[data-course-id="${courseId}"]`);
            const dataAttributes = courseRow.dataset;

            document.getElementById('editCourseId').value = courseId;
            document.getElementById('editCourseName').value = dataAttributes.courseName;
            document.getElementById('editCourseShortName').value = dataAttributes.courseShort;
            document.getElementById('editCourseDescription').value = dataAttributes.courseDescription;
            document.getElementById('editCourseLevel').value = dataAttributes.courseLevel;
            document.getElementById('editCourseDuration').value = dataAttributes.courseDuration;
            document.getElementById('editCourseCredits').value = dataAttributes.courseCredits;
            document.getElementById('editCourseSortOrder').value = dataAttributes.courseSort;
            document.getElementById('editCourseCollege').value = dataAttributes.courseCollege;
            document.getElementById('editCourseIsActive').checked = dataAttributes.courseActive === '1';
            document.getElementById('editCurrentCourseLogo').value = dataAttributes.courseLogo; // Get current logo URL

            // Show current logo in preview
            const editCoursePreviewImage = document.getElementById('editCoursePreviewImage');
            const editCourseLogoPreview = document.getElementById('editCourseLogoPreview');
            const editCourseLogoPlaceholder = document.getElementById('editCourseLogoPlaceholder');
            if (dataAttributes.courseLogo) {
                editCoursePreviewImage.src = '../' + dataAttributes.courseLogo;
                editCourseLogoPreview.classList.remove('hidden');
                editCourseLogoPlaceholder.classList.add('hidden');
            } else {
                editCoursePreviewImage.src = '';
                editCourseLogoPreview.classList.add('hidden');
                editCourseLogoPlaceholder.classList.remove('hidden');
            }

            document.getElementById('editCourseForm').action = 'manage-colleges.php'; // Set action to update
            document.getElementById('editCourseForm').target = '_self'; // Open in the same window
            document.getElementById('editCourseModal').classList.remove('hidden');
        }

        function closeEditCourseModal() {
            const editModal = document.getElementById('editCourseModal');
            if (editModal) {
                editModal.classList.add('hidden');
            }
            document.getElementById('editCourseForm').reset();
            document.getElementById('editCurrentCourseLogo').value = '';
            document.getElementById('editCoursePreviewImage').src = '';
            document.getElementById('editCourseLogoPreview').classList.add('hidden');
            document.getElementById('editCourseLogoPlaceholder').classList.remove('hidden');
        }

        function confirmDeleteCollege(collegeId, collegeName) {
            const deleteModal = document.getElementById('deleteCollegeModal');
            const collegeIdField = document.getElementById('deleteCollegeId');
            const collegeNameField = document.getElementById('deleteCollegeName');
            if (deleteModal && collegeIdField && collegeNameField) {
                collegeIdField.value = collegeId;
                collegeNameField.textContent = collegeName;
                deleteModal.classList.remove('hidden');
            }
        }

        function confirmDeleteCourse(courseId, courseName) {
            const deleteModal = document.getElementById('deleteCourseModal');
            const courseIdField = document.getElementById('deleteCourseId');
            const courseNameField = document.getElementById('deleteCourseName');
            if (deleteModal && courseIdField && courseNameField) {
                courseIdField.value = courseId;
                courseNameField.textContent = courseName;
                deleteModal.classList.remove('hidden');
            }
        }

        function closeDeleteCollegeModal() {
            const deleteModal = document.getElementById('deleteCollegeModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
            document.getElementById('deleteCollegeId').value = '';
            document.getElementById('deleteCollegeName').textContent = '';
        }

        function closeDeleteCourseModal() {
            const deleteModal = document.getElementById('deleteCourseModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
            document.getElementById('deleteCourseId').value = '';
            document.getElementById('deleteCourseName').textContent = '';
        }

        function closeEditCollegeModal() {
            const editModal = document.getElementById('editCollegeModal');
            if (editModal) {
                editModal.classList.add('hidden');
            }
            document.getElementById('editCollegeForm').action = 'manage-colleges.php'; // Reset action
            document.getElementById('editCollegeForm').target = '_self'; // Reset target
            document.getElementById('editCollegeForm').reset(); // Reset form fields
            document.getElementById('editCurrentLogo').value = ''; // Clear current logo URL
            document.getElementById('editPreviewImage').src = ''; // Clear preview image
            document.getElementById('editLogoPreview').classList.add('hidden'); // Hide preview
            document.getElementById('editLogoPlaceholder').classList.remove('hidden'); // Show placeholder
        }

        // Close modals when clicking outside
        const editCollegeModal = document.getElementById('editCollegeModal');
        const editCourseModal = document.getElementById('editCourseModal');
        const deleteCollegeModal = document.getElementById('deleteCollegeModal');
        const deleteCourseModal = document.getElementById('deleteCourseModal');

        if (editCollegeModal) {
            editCollegeModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditCollegeModal();
                }
            });
        }

        if (editCourseModal) {
            editCourseModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditCourseModal();
                }
            });
        }

        if (deleteCollegeModal) {
            deleteCollegeModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteCollegeModal();
                }
            });
        }

        if (deleteCourseModal) {
            deleteCourseModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteCourseModal();
                }
            });
        }
    </script>
</body>
</html>