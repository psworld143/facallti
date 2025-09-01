<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug logging
    error_log("Faculty form submission received: " . print_r($_POST, true));
    error_log("Files received: " . print_r($_FILES, true));
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $position = sanitize_input($_POST['position']);
                $department = sanitize_input($_POST['department']);
                $email = sanitize_input($_POST['email']);
                $password = $_POST['password'];
                $bio = $_POST['bio']; // Don't sanitize HTML content
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                // Check if email already exists (email is unique and will be used as username)
                $check_email_query = "SELECT id FROM faculty WHERE email = ?";
                $check_email_stmt = mysqli_prepare($conn, $check_email_query);
                mysqli_stmt_bind_param($check_email_stmt, "s", $email);
                mysqli_stmt_execute($check_email_stmt);
                $check_email_result = mysqli_stmt_get_result($check_email_stmt);

                if (mysqli_num_rows($check_email_result) > 0) {
                    $message = "Email already exists. Please use a different email address.";
                    $message_type = "error";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Handle image upload
                    $image_url = '';
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/faculty/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                        if (in_array($file_extension, $allowed_extensions)) {
                            $filename = uniqid() . '.' . $file_extension;
                            $upload_path = $upload_dir . $filename;

                            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                                $image_url = 'uploads/faculty/' . $filename;
                            }
                        }
                    }

                    if (!empty($first_name) && !empty($last_name) && !empty($email)) {
                        $query = "INSERT INTO faculty (first_name, last_name, position, department, email, password, bio, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "ssssssssi", $first_name, $last_name, $position, $department, $email, $hashed_password, $bio, $image_url, $is_active);

                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Faculty member added successfully!";
                            $message_type = "success";
                            error_log("Faculty member added successfully: " . $email);
                        } else {
                            $error_msg = mysqli_stmt_error($stmt);
                            $message = "Error adding faculty member: " . $error_msg;
                            $message_type = "error";
                            error_log("Error adding faculty member: " . $error_msg);
                        }
                    } else {
                        $message = "First name, last name, and email are required.";
                        $message_type = "error";
                    }
                }
                break;

            case 'update':
                $id = (int)$_POST['id'];
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $position = sanitize_input($_POST['position']);
                $department = sanitize_input($_POST['department']);
                $email = sanitize_input($_POST['email']);
                $password = $_POST['password'];
                $bio = $_POST['bio'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                // Check if email already exists (excluding current faculty member)
                $check_email_query = "SELECT id FROM faculty WHERE email = ? AND id != ?";
                $check_email_stmt = mysqli_prepare($conn, $check_email_query);
                mysqli_stmt_bind_param($check_email_stmt, "si", $email, $id);
                mysqli_stmt_execute($check_email_stmt);
                $check_email_result = mysqli_stmt_get_result($check_email_stmt);

                if (mysqli_num_rows($check_email_result) > 0) {
                    $message = "Email already exists. Please use a different email address.";
                    $message_type = "error";
                } else {
                    // Handle image upload
                    $image_url = $_POST['current_image'];
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/faculty/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                        if (in_array($file_extension, $allowed_extensions)) {
                            $filename = uniqid() . '.' . $file_extension;
                            $upload_path = $upload_dir . $filename;

                            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                                // Delete old image if exists
                                if (!empty($_POST['current_image']) && file_exists('../' . $_POST['current_image'])) {
                                    unlink('../' . $_POST['current_image']);
                                }
                                $image_url = 'uploads/faculty/' . $filename;
                            }
                        }
                    }

                    if (!empty($first_name) && !empty($last_name) && !empty($email)) {
                        // Check if password was changed
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $query = "UPDATE faculty SET first_name = ?, last_name = ?, position = ?, department = ?, email = ?, password = ?, bio = ?, image_url = ?, is_active = ? WHERE id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "ssssssssii", $first_name, $last_name, $position, $department, $email, $hashed_password, $bio, $image_url, $is_active, $id);
                        } else {
                            $query = "UPDATE faculty SET first_name = ?, last_name = ?, position = ?, department = ?, email = ?, bio = ?, image_url = ?, is_active = ? WHERE id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "sssssssii", $first_name, $last_name, $position, $department, $email, $bio, $image_url, $is_active, $id);
                        }

                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Faculty member updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error updating faculty member.";
                            $message_type = "error";
                        }
                    } else {
                        $message = "First name, last name, and email are required.";
                        $message_type = "error";
                    }
                }
                break;

            case 'delete':
                $id = (int)$_POST['id'];

                // Get image path before deleting
                $query = "SELECT image_url FROM faculty WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $faculty = mysqli_fetch_assoc($result);

                // Delete image file if exists
                if ($faculty && !empty($faculty['image_url']) && file_exists('../' . $faculty['image_url'])) {
                    unlink('../' . $faculty['image_url']);
                }

                $query = "DELETE FROM faculty WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Faculty member deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting faculty member.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get faculty members
$query = "SELECT * FROM faculty ORDER BY last_name ASC, first_name ASC";
$faculty_result = mysqli_query($conn, $query);

// Get colleges for department dropdown
$colleges_query = "SELECT id, name, short_name FROM colleges WHERE is_active = 1 ORDER BY sort_order, name";
$colleges_result = mysqli_query($conn, $colleges_query);
$colleges = [];
while ($row = mysqli_fetch_assoc($colleges_result)) {
    $colleges[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Management - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
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
    <script src="https://cdn.ckeditor.com/ckeditor5/27.1.0/classic/ckeditor.js"></script>
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
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-seait-dark mb-2">Faculty Management</h1>
                        <p class="text-gray-600">Manage faculty members and staff</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="showAddModal()" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-plus mr-2"></i>Add Faculty Member
                        </button>
                        <a href="debug_faculty.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            <i class="fas fa-bug mr-2"></i>Debug
                        </a>
                        <a href="test_db_connection.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition">
                            <i class="fas fa-database mr-2"></i>Test DB
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Information Alert -->
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Faculty Management Updates</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p><strong>Department Selection:</strong> Department options are now populated from the colleges database. Each option shows the full college name with its abbreviation in parentheses.</p>
                            <p class="mt-1"><strong>Login Credentials:</strong> Faculty members can login using their email address or QR code as their login username with the default password "Seait123" which can be changed during editing.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Faculty Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($faculty = mysqli_fetch_assoc($faculty_result)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-16 h-16 rounded-full overflow-hidden mr-4">
                                <?php if (!empty($faculty['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($faculty['image_url']); ?>"
                                     alt="<?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>"
                                     class="w-full h-full object-cover">
                                <?php else: ?>
                                <div class="w-full h-full bg-gray-300 flex items-center justify-center">
                                    <i class="fas fa-user text-gray-500 text-xl"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($faculty['position']); ?></p>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php echo $faculty['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $faculty['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2 text-sm text-gray-600 mb-4">
                            <div class="flex justify-between">
                                <span>Department:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($faculty['department']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Email:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($faculty['email']); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($faculty['bio'])): ?>
                        <div class="text-sm text-gray-600 mb-4">
                            <?php echo substr(strip_tags($faculty['bio']), 0, 100) . '...'; ?>
                        </div>
                        <?php endif; ?>

                        <div class="flex space-x-2">
                            <button onclick="editFaculty(<?php echo $faculty['id']; ?>)"
                                    class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition text-sm">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="deleteFaculty(<?php echo $faculty['id']; ?>)"
                                    class="flex-1 bg-red-600 text-white px-3 py-2 rounded-md hover:bg-red-700 transition text-sm">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Faculty Modal -->
    <div id="facultyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Add Faculty Member</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="facultyForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="facultyId">
                    <input type="hidden" name="current_image" id="currentImage">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" name="first_name" id="facultyFirstName" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="last_name" id="facultyLastName" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                            <input type="text" name="position" id="facultyPosition" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select name="department" id="facultyDepartment" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select Department</option>
                                <?php foreach ($colleges as $college): ?>
                                    <option value="<?php echo htmlspecialchars($college['name']); ?>">
                                        <?php echo htmlspecialchars($college['name']); ?> (<?php echo htmlspecialchars($college['short_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" id="facultyEmail" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <p class="text-xs text-gray-500 mt-1">Email will be used as the login username</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <input type="text" name="password" id="facultyPassword" value="Seait123" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <p class="text-xs text-gray-500 mt-1">Default password: Seait123 (can be changed)</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="facultyActive" class="mr-2">
                            <label for="facultyActive" class="text-sm font-medium text-gray-700">Active</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Profile Image</label>
                        <input type="file" name="image" id="facultyImage" accept="image/*"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <p class="text-xs text-gray-500 mt-1">Upload a profile image (JPG, PNG, GIF)</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                        <textarea name="bio" id="facultyBio" rows="6"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-save mr-2"></i>Save Faculty Member
                        </button>
                        <button type="button" onclick="closeModal()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Faculty Confirmation Modal -->
    <div id="deleteFacultyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Faculty Member</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this faculty member? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Faculty profile will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible to students
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
                        <input type="hidden" name="id" id="deleteFacultyId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteFacultyModal()"
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
        // Add form submission debugging
        document.addEventListener('DOMContentLoaded', function() {
            const facultyForm = document.getElementById('facultyForm');
            if (facultyForm) {
                facultyForm.addEventListener('submit', function(e) {
                    console.log('Form submission started');
                    console.log('Form action:', this.action);
                    console.log('Form method:', this.method);
                    
                    // Log form data
                    const formData = new FormData(this);
                    for (let [key, value] of formData.entries()) {
                        console.log(key + ': ' + value);
                    }
                });
            }
        });

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Faculty Member';
            document.getElementById('formAction').value = 'add';
            document.getElementById('facultyForm').reset();
            document.getElementById('facultyId').value = '';
            document.getElementById('currentImage').value = '';
            document.getElementById('facultyPassword').value = 'Seait123'; // Set default password
            document.getElementById('facultyModal').classList.remove('hidden');
        }

        function editFaculty(id) {
            // Fetch faculty data via AJAX
            fetch(`get_faculty.php?id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    document.getElementById('modalTitle').textContent = 'Edit Faculty Member';
                    document.getElementById('formAction').value = 'update';
                    document.getElementById('facultyId').value = data.id;
                    document.getElementById('facultyFirstName').value = data.first_name;
                    document.getElementById('facultyLastName').value = data.last_name;
                    document.getElementById('facultyPosition').value = data.position;
                    document.getElementById('facultyDepartment').value = data.department;
                    document.getElementById('facultyEmail').value = data.email;
                    document.getElementById('facultyPassword').value = ''; // Clear password for editing
                    document.getElementById('facultyBio').value = data.bio;
                    document.getElementById('facultyActive').checked = data.is_active == 1;
                    document.getElementById('currentImage').value = data.image_url;
                    document.getElementById('facultyModal').classList.remove('hidden');
                })
                .catch(error => {
                    console.error('Error fetching faculty data:', error);
                    alert('Network error. Please try again. Error: ' + error.message);
                });
        }

        function deleteFaculty(id) {
            document.getElementById('deleteFacultyId').value = id;
            document.getElementById('deleteFacultyModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('facultyModal').classList.add('hidden');
        }

        function closeDeleteFacultyModal() {
            document.getElementById('deleteFacultyModal').classList.add('hidden');
        }

        // Close delete faculty modal when clicking outside
        const deleteFacultyModal = document.getElementById('deleteFacultyModal');
        if (deleteFacultyModal) {
            deleteFacultyModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteFacultyModal();
                }
            });
        }
    </script>
</body>
</html>