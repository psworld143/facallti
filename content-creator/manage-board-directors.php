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
            case 'add_director':
                $name = trim($_POST['name']);
                $position = trim($_POST['position']);
                $bio = trim($_POST['bio']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $linkedin_url = trim($_POST['linkedin_url']);
                $sort_order = (int)$_POST['sort_order'];

                // Handle photo upload
                $photo_url = '';
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                    $upload_dir = '../assets/images/directors/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'director_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;

                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                            $photo_url = 'assets/images/directors/' . $filename;
                        } else {
                            $error = 'Failed to upload photo.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                    }
                }

                if (empty($name) || empty($position)) {
                    $error = 'Name and position are required.';
                } else {
                    $query = "INSERT INTO board_directors (name, position, bio, photo_url, email, phone, linkedin_url, sort_order, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'sssssssii', $name, $position, $bio, $photo_url, $email, $phone, $linkedin_url, $sort_order, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Board member added successfully!';
                    } else {
                        $error = 'Error adding board member: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'update_director':
                $director_id = (int)$_POST['director_id'];
                $name = trim($_POST['name']);
                $position = trim($_POST['position']);
                $bio = trim($_POST['bio']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $linkedin_url = trim($_POST['linkedin_url']);
                $sort_order = (int)$_POST['sort_order'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                // Handle photo upload for update
                $photo_url = $_POST['current_photo']; // Keep existing photo if no new one uploaded
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                    $upload_dir = '../assets/images/directors/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'director_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;

                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                            $photo_url = 'assets/images/directors/' . $filename;
                        } else {
                            $error = 'Failed to upload photo.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                    }
                }

                $query = "UPDATE board_directors SET name = ?, position = ?, bio = ?, photo_url = ?, email = ?, phone = ?, linkedin_url = ?, sort_order = ?, is_active = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'sssssssiii', $name, $position, $bio, $photo_url, $email, $phone, $linkedin_url, $sort_order, $is_active, $director_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Board member updated successfully!';
                } else {
                    $error = 'Error updating board member: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'delete_director':
                $director_id = (int)$_POST['director_id'];

                $delete_query = "DELETE FROM board_directors WHERE id = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, "i", $director_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Board member deleted successfully!';
                } else {
                    $error = 'Error deleting board member: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Fetch board directors
$directors_query = "SELECT * FROM board_directors ORDER BY sort_order ASC, name ASC";
$directors_result = mysqli_query($conn, $directors_query);

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM board_directors";
$count_result = mysqli_query($conn, $count_query);
$total = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total / $items_per_page);

// Fetch paginated directors
$offset = ($page - 1) * $items_per_page;
$directors_query = "SELECT * FROM board_directors ORDER BY sort_order ASC, name ASC LIMIT $items_per_page OFFSET $offset";
$directors_result = mysqli_query($conn, $directors_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Board Directors - SEAIT Content Creator</title>
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
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Manage Board Directors</h1>
                    <p class="text-gray-600">Add and manage board members for the About section</p>
                </div>

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

            <!-- Information Section -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Board Directors Management</h3>
                        <div class="text-sm text-blue-700 space-y-1">
                            <p><strong>Add Directors:</strong> Add new board members with photos, contact information, and professional bios.</p>
                            <p><strong>Manage Information:</strong> Update director details, photos, and contact information.</p>
                            <p><strong>Organization:</strong> Use sort order to control the display order on the website.</p>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Add Director Form -->
        <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-6 lg:mb-8">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-full mr-3">
                        <i class="fas fa-user-tie text-purple-600"></i>
                    </div>
                    <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add New Board Member</h2>
                </div>
                <button type="button" onclick="toggleForm('director-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                    <i class="fas fa-chevron-down" id="director-toggle-icon"></i>
                </button>
            </div>
            <form method="POST" id="director-form" class="space-y-4" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_director">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required placeholder="Enter full name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Position <span class="text-red-500">*</span></label>
                        <input type="text" name="position" required placeholder="e.g., Chairman of the Board" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" placeholder="email@example.com" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" placeholder="+63 912 345 6789" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">LinkedIn URL</label>
                    <input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/username" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                    <textarea name="bio" rows="4" placeholder="Enter professional biography and background" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
                        <input type="file" name="photo" id="directorPhoto" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" onchange="previewPhoto(this)">
                        <p class="text-xs text-gray-500 mt-1">Recommended size: 300x300px. Supported formats: JPG, PNG, GIF.</p>
                        <div id="photoPreview" class="mt-2 hidden">
                            <img id="previewImage" src="" alt="Photo Preview" class="w-20 h-20 object-cover border border-gray-300 rounded">
                            <p class="text-xs text-gray-500 mt-1">Photo preview</p>
                        </div>
                        <div id="photoPlaceholder" class="mt-2">
                            <div class="w-20 h-20 bg-gray-100 border border-gray-300 rounded flex items-center justify-center">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">No photo selected</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                        <i class="fas fa-plus mr-2"></i>Add Board Member
                    </button>
                </div>
            </form>
        </div>

        <!-- Directors List -->
        <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
            <h2 class="text-lg lg:text-xl font-semibold text-seait-dark mb-4">Manage Board Directors</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                            <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($director = mysqli_fetch_assoc($directors_result)): ?>
                        <tr data-director-id="<?php echo $director['id']; ?>" data-director-name="<?php echo htmlspecialchars($director['name'] ?? ''); ?>" data-director-position="<?php echo htmlspecialchars($director['position'] ?? ''); ?>" data-director-bio="<?php echo htmlspecialchars($director['bio'] ?? ''); ?>" data-director-email="<?php echo htmlspecialchars($director['email'] ?? ''); ?>" data-director-phone="<?php echo htmlspecialchars($director['phone'] ?? ''); ?>" data-director-linkedin="<?php echo htmlspecialchars($director['linkedin_url'] ?? ''); ?>" data-director-sort="<?php echo $director['sort_order']; ?>" data-director-active="<?php echo $director['is_active']; ?>" data-director-photo="<?php echo htmlspecialchars($director['photo_url'] ?? ''); ?>">
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($director['photo_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($director['photo_url'] ?? ''); ?>" alt="<?php echo htmlspecialchars($director['name'] ?? ''); ?> Photo" class="h-12 w-12 object-cover rounded-full">
                                <?php else: ?>
                                <div class="h-12 w-12 bg-gray-200 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($director['name'] ?? ''); ?></div>
                                <div class="text-xs text-gray-500 hidden sm:block"><?php echo htmlspecialchars(substr($director['bio'] ?? '', 0, 50)) . (strlen($director['bio'] ?? '') > 50 ? '...' : ''); ?></div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($director['position'] ?? ''); ?></td>
                            <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if (!empty($director['email'])): ?>
                                <div><?php echo htmlspecialchars($director['email'] ?? ''); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($director['phone'])): ?>
                                <div class="text-gray-500"><?php echo htmlspecialchars($director['phone'] ?? ''); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $director['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $director['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs lg:text-sm font-medium">
                                <button onclick="editDirector(<?php echo $director['id']; ?>)" class="text-seait-orange hover:text-orange-600 mr-3">Edit</button>
                                <button onclick="confirmDeleteDirector(<?php echo $director['id']; ?>, '<?php echo htmlspecialchars($director['name'] ?? ''); ?>')" class="text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                <div class="text-xs lg:text-sm text-gray-700">
                    Showing <?php echo (($page - 1) * $items_per_page) + 1; ?> to <?php echo min($page * $items_per_page, $total); ?> of <?php echo $total; ?> directors
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-2 text-xs lg:text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="px-3 py-2 text-xs lg:text-sm rounded transition <?php echo $i == $page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-2 text-xs lg:text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
            </div>
        </div>
    </div>

    <!-- Edit Director Modal -->
    <div id="editDirectorModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-4 sm:top-20 mx-auto p-4 sm:p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Board Member</h3>
                <form id="editDirectorForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_director">
                    <input type="hidden" name="director_id" id="editDirectorId">
                    <input type="hidden" name="current_photo" id="editCurrentPhoto">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                            <input type="text" name="name" id="editDirectorName" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Position *</label>
                            <input type="text" name="position" id="editDirectorPosition" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" id="editDirectorEmail" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input type="tel" name="phone" id="editDirectorPhone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn URL</label>
                            <input type="url" name="linkedin_url" id="editDirectorLinkedin" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="editDirectorSortOrder" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                            <textarea name="bio" id="editDirectorBio" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
                            <input type="file" name="photo" id="editDirectorPhoto" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" onchange="previewEditPhoto(this)">
                            <p class="text-xs text-gray-500 mt-1">Recommended size: 300x300px. Supported formats: JPG, PNG, GIF.</p>
                            <div id="editPhotoPreview" class="mt-2">
                                <img id="editPreviewImage" src="" alt="Photo Preview" class="w-20 h-20 object-cover border border-gray-300 rounded">
                                <p class="text-xs text-gray-500 mt-1">Current photo</p>
                            </div>
                            <div id="editPhotoPlaceholder" class="mt-2">
                                <div class="w-20 h-20 bg-gray-100 border border-gray-300 rounded flex items-center justify-center">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">No photo uploaded</p>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" id="editDirectorIsActive" class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <span class="ml-2 block text-sm text-gray-900">Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 mt-6">
                        <button type="button" onclick="closeEditDirectorModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition w-full sm:w-auto">Cancel</button>
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition w-full sm:w-auto">Update Director</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Director Confirmation Modal -->
    <div id="deleteDirectorModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Board Member</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteDirectorName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Board member will be permanently removed
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
                    <form id="deleteDirectorForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_director">
                        <input type="hidden" name="director_id" id="deleteDirectorId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteDirectorModal()"
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

        function previewPhoto(input) {
            const previewImage = document.getElementById('previewImage');
            const photoPreview = document.getElementById('photoPreview');
            const photoPlaceholder = document.getElementById('photoPlaceholder');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    photoPreview.classList.remove('hidden');
                    photoPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                previewImage.src = '';
                photoPreview.classList.add('hidden');
                photoPlaceholder.classList.remove('hidden');
            }
        }

        function previewEditPhoto(input) {
            const previewImage = document.getElementById('editPreviewImage');
            const editPhotoPreview = document.getElementById('editPhotoPreview');
            const editPhotoPlaceholder = document.getElementById('editPhotoPlaceholder');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    editPhotoPreview.classList.remove('hidden');
                    editPhotoPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                // If no file selected, show the current photo or placeholder
                const currentPhoto = document.getElementById('editCurrentPhoto').value;
                if (currentPhoto) {
                    previewImage.src = '../' + currentPhoto;
                    editPhotoPreview.classList.remove('hidden');
                    editPhotoPlaceholder.classList.add('hidden');
                } else {
                    previewImage.src = '';
                    editPhotoPreview.classList.add('hidden');
                    editPhotoPlaceholder.classList.remove('hidden');
                }
            }
        }

        function editDirector(directorId) {
            // Fetch director details for editing
            const directorRow = document.querySelector(`tr[data-director-id="${directorId}"]`);
            const dataAttributes = directorRow.dataset;

            document.getElementById('editDirectorId').value = directorId;
            document.getElementById('editDirectorName').value = dataAttributes.directorName;
            document.getElementById('editDirectorPosition').value = dataAttributes.directorPosition;
            document.getElementById('editDirectorBio').value = dataAttributes.directorBio;
            document.getElementById('editDirectorEmail').value = dataAttributes.directorEmail;
            document.getElementById('editDirectorPhone').value = dataAttributes.directorPhone;
            document.getElementById('editDirectorLinkedin').value = dataAttributes.directorLinkedin;
            document.getElementById('editDirectorSortOrder').value = dataAttributes.directorSort;
            document.getElementById('editCurrentPhoto').value = dataAttributes.directorPhoto;
            document.getElementById('editDirectorIsActive').checked = dataAttributes.directorActive === '1';

            // Show current photo in preview
            const editPreviewImage = document.getElementById('editPreviewImage');
            const editPhotoPreview = document.getElementById('editPhotoPreview');
            const editPhotoPlaceholder = document.getElementById('editPhotoPlaceholder');
            if (dataAttributes.directorPhoto) {
                editPreviewImage.src = '../' + dataAttributes.directorPhoto;
                editPhotoPreview.classList.remove('hidden');
                editPhotoPlaceholder.classList.add('hidden');
            } else {
                editPreviewImage.src = '';
                editPhotoPreview.classList.add('hidden');
                editPhotoPlaceholder.classList.remove('hidden');
            }

            document.getElementById('editDirectorModal').classList.remove('hidden');
        }

        function confirmDeleteDirector(directorId, directorName) {
            const deleteModal = document.getElementById('deleteDirectorModal');
            const directorIdField = document.getElementById('deleteDirectorId');
            const directorNameField = document.getElementById('deleteDirectorName');
            if (deleteModal && directorIdField && directorNameField) {
                directorIdField.value = directorId;
                directorNameField.textContent = directorName;
                deleteModal.classList.remove('hidden');
            }
        }

        function closeEditDirectorModal() {
            const editModal = document.getElementById('editDirectorModal');
            if (editModal) {
                editModal.classList.add('hidden');
            }
            document.getElementById('editDirectorForm').reset();
            document.getElementById('editCurrentPhoto').value = '';
            document.getElementById('editPreviewImage').src = '';
            document.getElementById('editPhotoPreview').classList.add('hidden');
            document.getElementById('editPhotoPlaceholder').classList.remove('hidden');
        }

        function closeDeleteDirectorModal() {
            const deleteModal = document.getElementById('deleteDirectorModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
            document.getElementById('deleteDirectorId').value = '';
            document.getElementById('deleteDirectorName').textContent = '';
        }

        // Close modals when clicking outside
        const editModal = document.getElementById('editDirectorModal');
        const deleteModal = document.getElementById('deleteDirectorModal');

        if (editModal) {
            editModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditDirectorModal();
                }
            });
        }

        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteDirectorModal();
                }
            });
        }
    </script>
</body>
</html>