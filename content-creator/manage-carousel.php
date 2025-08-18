<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Helper function to ensure carousel upload directory is properly set up
function ensureCarouselUploadDirectory() {
    // Get the absolute path to the project root
    $project_root = dirname(dirname(__FILE__));
    $upload_dir = $project_root . '/assets/images/carousel/';

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory: ' . $upload_dir];
        }
    }

    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        // Try to make it writable with more permissive settings
        if (!chmod($upload_dir, 0777)) {
            return ['success' => false, 'error' => 'Upload directory is not writable. Please ensure the web server has write permissions to: ' . $upload_dir];
        }
    }

    return ['success' => true, 'path' => $upload_dir];
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$error = '';

// Handle form submission for adding/editing carousel slide
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $title = $_POST['title'];
            $subtitle = $_POST['subtitle'];
            $description = $_POST['description'];
            $button_text = $_POST['button_text'];
            $button_link = $_POST['button_link'];
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Handle image upload
            $image_url = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                // Debug information
                error_log("Carousel upload attempt - File: " . $_FILES['image']['name']);
                error_log("Carousel upload attempt - Temp file: " . $_FILES['image']['tmp_name']);

                // Ensure upload directory is properly set up
                $dir_check = ensureCarouselUploadDirectory();
                if (!$dir_check['success']) {
                    $error = $dir_check['error'];
                    error_log("Carousel upload error - Directory issue: " . $error);
                } else {
                    $upload_dir = $dir_check['path'];
                    error_log("Carousel upload - Directory path: " . $upload_dir);

                    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'carousel_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;
                        error_log("Carousel upload - Target path: " . $upload_path);

                        // Add error checking for file upload
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                            $image_url = 'assets/images/carousel/' . $filename;
                            error_log("Carousel upload success - File saved as: " . $image_url);
                        } else {
                            $upload_error = error_get_last();
                            $error_msg = $upload_error ? $upload_error['message'] : 'Unknown error';
                            $error = 'Failed to upload image. Error: ' . $error_msg;
                            error_log("Carousel upload error - " . $error);
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                        error_log("Carousel upload error - Invalid file type: " . $file_extension);
                    }
                }
            }

            if (empty($error)) {
                $query = "INSERT INTO carousel_slides (title, subtitle, description, image_url, button_text, button_link, sort_order, is_active, status, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'pending', ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'ssssssis', $title, $subtitle, $description, $image_url, $button_text, $button_link, $sort_order, $_SESSION['user_id']);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Carousel slide added successfully! It will be reviewed by the Social Media Manager.';
                } else {
                    $error = 'Failed to add carousel slide.';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['slide_id'];
            $title = $_POST['title'];
            $subtitle = $_POST['subtitle'];
            $description = $_POST['description'];
            $button_text = $_POST['button_text'];
            $button_link = $_POST['button_link'];
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Handle image upload for edit
            $image_url = $_POST['current_image'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                // Debug information
                error_log("Carousel edit upload attempt - File: " . $_FILES['image']['name']);
                error_log("Carousel edit upload attempt - Temp file: " . $_FILES['image']['tmp_name']);

                // Ensure upload directory is properly set up
                $dir_check = ensureCarouselUploadDirectory();
                if (!$dir_check['success']) {
                    $error = $dir_check['error'];
                    error_log("Carousel edit upload error - Directory issue: " . $error);
                } else {
                    $upload_dir = $dir_check['path'];
                    error_log("Carousel edit upload - Directory path: " . $upload_dir);

                    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'carousel_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;
                        error_log("Carousel edit upload - Target path: " . $upload_path);

                        // Add error checking for file upload
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                            $image_url = 'assets/images/carousel/' . $filename;
                            error_log("Carousel edit upload success - File saved as: " . $image_url);
                        } else {
                            $upload_error = error_get_last();
                            $error_msg = $upload_error ? $upload_error['message'] : 'Unknown error';
                            $error = 'Failed to upload image. Error: ' . $error_msg;
                            error_log("Carousel edit upload error - " . $error);
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                        error_log("Carousel edit upload error - Invalid file type: " . $file_extension);
                    }
                }
            }

            if (empty($error)) {
                $query = "UPDATE carousel_slides SET title = ?, subtitle = ?, description = ?, image_url = ?, button_text = ?, button_link = ?, sort_order = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'ssssssii', $title, $subtitle, $description, $image_url, $button_text, $button_link, $sort_order, $id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Carousel slide updated successfully!';
                } else {
                    $error = 'Failed to update carousel slide.';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['slide_id'];
            $query = "DELETE FROM carousel_slides WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Carousel slide deleted successfully!';
            } else {
                $error = 'Failed to delete carousel slide.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch existing carousel slides
$slides_query = "SELECT * FROM carousel_slides ORDER BY sort_order ASC, created_at DESC";
$slides_result = mysqli_query($conn, $slides_query);

// Pagination settings
$items_per_page = 10;
$slides_page = isset($_GET['slides_page']) ? (int)$_GET['slides_page'] : 1;

// Get total count
$slides_count_query = "SELECT COUNT(*) as total FROM carousel_slides";
$slides_count_result = mysqli_query($conn, $slides_count_query);
$slides_total = mysqli_fetch_assoc($slides_count_result)['total'];
$slides_total_pages = ceil($slides_total / $items_per_page);

// Fetch paginated slides
$slides_offset = ($slides_page - 1) * $items_per_page;
$slides_query = "SELECT * FROM carousel_slides ORDER BY sort_order ASC, created_at DESC LIMIT $items_per_page OFFSET $slides_offset";
$slides_result = mysqli_query($conn, $slides_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Carousel - SEAIT Content Creator</title>
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
                        <p class="text-sm text-gray-600">Welcome, <?php echo $_SESSION['first_name']; ?></p>
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
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Manage Carousel</h1>
                    <p class="text-gray-600">Create and manage carousel slides for the homepage</p>
                </div>

            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Information Section -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Carousel Management</h3>
                        <div class="text-sm text-blue-700 space-y-1">
                            <p><strong>Homepage Slides:</strong> Create and manage carousel slides that appear on the homepage to showcase important information.</p>
                            <p><strong>Image Requirements:</strong> Upload high-quality images with recommended dimensions. Images will be automatically resized for optimal display.</p>
                            <p><strong>Content:</strong> Add compelling titles and subtitles to engage visitors and highlight key messages.</p>
                            <p><strong>Organization:</strong> Use sort order to control the display sequence of slides. Active slides will be shown on the homepage.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add New Carousel Button -->
            <div class="mb-6">
                <button onclick="openAddModal()" class="bg-seait-orange text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition-all duration-200 font-medium flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Add New Carousel Slide
                </button>
            </div>

            <!-- Carousel Slides List -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtitle</th>
                                <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($slide = mysqli_fetch_assoc($slides_result)): ?>
                            <tr>
                                <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                    <img src="../<?php echo htmlspecialchars($slide['image_url']); ?>" alt="<?php echo nl2br(htmlspecialchars($slide['title'])); ?>" class="h-12 w-16 lg:h-16 lg:w-24 object-cover rounded">
                                </td>
                                <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                    <div class="text-xs lg:text-sm font-medium text-gray-900"><?php echo nl2br(htmlspecialchars($slide['title'])); ?></div>
                                </td>
                                <td class="hidden lg:table-cell px-6 py-4">
                                    <div class="text-sm text-gray-500 break-words max-w-xs"><?php echo nl2br(htmlspecialchars($slide['subtitle'])); ?></div>
                                </td>
                                <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $slide['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $slide['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($slide)); ?>)" class="text-seait-orange hover:text-orange-600 p-1">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteSlide(<?php echo $slide['id']; ?>)" class="text-red-600 hover:text-red-900 p-1">
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

            <!-- Carousel Slides Pagination -->
            <?php if ($slides_total_pages > 1): ?>
            <div class="mt-6 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <?php echo (($slides_page - 1) * $items_per_page) + 1; ?> to <?php echo min($slides_page * $items_per_page, $slides_total); ?> of <?php echo $slides_total; ?> slides
                </div>
                <div class="flex space-x-2">
                    <?php if ($slides_page > 1): ?>
                    <a href="?slides_page=<?php echo $slides_page - 1; ?>" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $slides_page - 2); $i <= min($slides_total_pages, $slides_page + 2); $i++): ?>
                    <a href="?slides_page=<?php echo $i; ?>" class="px-3 py-2 text-sm rounded transition <?php echo $i == $slides_page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($slides_page < $slides_total_pages): ?>
                    <a href="?slides_page=<?php echo $slides_page + 1; ?>" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="slideModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-4 sm:top-20 mx-auto p-4 sm:p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Add New Carousel Slide</h3>
                <form id="slideForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="slide_id" id="slideId">
                    <input type="hidden" name="current_image" id="currentImage">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                            <input type="text" name="title" id="slideTitle" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subtitle</label>
                            <input type="text" name="subtitle" id="slideSubtitle" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" id="slideDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Button Text</label>
                            <input type="text" name="button_text" id="slideButtonText" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Button Link</label>
                            <input type="text" name="button_link" id="slideButtonLink" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="slideSortOrder" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Image *</label>
                            <input type="file" name="image" id="slideImage" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <p class="text-xs text-gray-500 mt-1">Recommended size: 1920x1080px. Max file size: 5MB.</p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition w-full sm:w-auto">Cancel</button>
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition w-full sm:w-auto">Save Slide</button>
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
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Carousel Slide</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this carousel slide? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Slide will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible on the carousel
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slide_id" id="deleteSlideId">
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
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Carousel Slide';
            document.getElementById('formAction').value = 'add';
            document.getElementById('slideForm').reset();
            document.getElementById('slideId').value = '';
            document.getElementById('currentImage').value = '';
            document.getElementById('slideModal').classList.remove('hidden');
        }

        function openEditModal(slide) {
            document.getElementById('modalTitle').textContent = 'Edit Carousel Slide';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('slideId').value = slide.id;
            document.getElementById('slideTitle').value = slide.title;
            document.getElementById('slideSubtitle').value = slide.subtitle;
            document.getElementById('slideDescription').value = slide.description;
            document.getElementById('slideButtonText').value = slide.button_text;
            document.getElementById('slideButtonLink').value = slide.button_link;
            document.getElementById('slideSortOrder').value = slide.sort_order;
            document.getElementById('currentImage').value = slide.image_url;
            document.getElementById('slideModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('slideModal').classList.add('hidden');
        }

        function deleteSlide(slideId) {
            const deleteModal = document.getElementById('deleteModal');
            const slideIdField = document.getElementById('deleteSlideId');
            if (deleteModal && slideIdField) {
                slideIdField.value = slideId;
                deleteModal.classList.remove('hidden');
            }
        }

        function closeDeleteModal() {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
        }

        // Close modals when clicking outside
        const modal = document.getElementById('slideModal');
        const deleteModal = document.getElementById('deleteModal');

        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        }

        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });
        }
    </script>
</body>
</html>